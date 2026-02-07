<?php
ob_start(); // Fix: Buffer output to prevent "Headers already sent" error
session_start(); // Fix: Start session before any HTML output

// Handle page view routing
$page = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR - Performance</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- CSS VARIABLES & RESET --- */
        :root {
            --sidebar-bg: #ffffff;
            --sidebar-text: #67748e;
            --sidebar-active-bg: #f8f9fa;
            --accent-color: #ff5b16; 
            --bg-body: #f7f7f7;
            --card-bg: #ffffff;
            --text-primary: #333333;
            --border-color: #e5e9f2;
            
            /* Status Colors */
            --status-open-bg: #e3f2fd;
            --status-open-text: #1976d2;
            --status-solved-bg: #e8f5e9;
            --status-solved-text: #2e7d32;
            --status-pending-bg: #fff3e0;
            --status-pending-text: #ef6c00;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: var(--bg-body); display: flex; height: 100vh; overflow: hidden; color: var(--text-primary); }

        /* --- MAIN CONTENT --- */
        .main-content { 
            flex: 1; 
            padding: 25px; 
            overflow-y: auto; 
            margin-left: 95px; /* Matches primary sidebar width */
            transition: margin-left 0.3s;
        }
        
        /* Class handled by sidebar JS */
        .main-content.main-shifted { margin-left: 315px; }

        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .breadcrumb { font-size: 0.8rem; color: #888; margin-bottom: 5px; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.9rem; color: white; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background-color: var(--accent-color); }
        .btn-primary:hover { background-color: #e64a0f; }
        .btn-dark { background-color: #333; color: #fff; }
        .btn-sm { padding: 4px 8px; font-size: 0.8rem; }

        .card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 25px; border: 1px solid #eee; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; color: #333; font-weight: 600; border-bottom: 2px solid #eee; font-size: 0.85rem; }
        td { padding: 15px 12px; border-bottom: 1px solid #f9f9f9; font-size: 0.85rem; color: #555; vertical-align: middle; }
        
        /* Form Elements */
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-size: 0.85rem; font-weight: 500; color: #333; }
        .form-control, .form-select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; font-size: 0.9rem; background-color: #fff; }
        .form-control:focus { border-color: var(--accent-color); }
        
        /* --- Performance Page Specifics --- */
        .profile-cell { display: flex; align-items: center; gap: 10px; }
        .profile-cell img { width: 30px; height: 30px; border-radius: 50%; }
        .status-badge { background: #39da8a; color: #fff; padding: 3px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
        .action-icons i { margin-left: 10px; cursor: pointer; color: #888; }
        .action-icons i:hover { color: var(--accent-color); }

        /* --- Performance Review Styles --- */
        .grade-span .badge { margin: 2px; padding: 8px 12px; font-weight: normal; border-radius: 4px; display: inline-block; font-size: 0.75rem; }
        .bg-inverse-danger { background: rgba(234, 84, 85, 0.1); color: #ea5455; }
        .bg-inverse-warning { background: rgba(255, 159, 67, 0.1); color: #ff9f43; }
        .bg-inverse-info { background: rgba(0, 207, 232, 0.1); color: #00cfe8; }
        .bg-inverse-purple { background: rgba(115, 103, 240, 0.1); color: #7367f0; }
        .bg-inverse-success { background: rgba(40, 199, 111, 0.1); color: #28c76f; }
        
        .row { display: flex; flex-wrap: wrap; margin-right: -10px; margin-left: -10px; }
        .col-md-3 { flex: 0 0 25%; max-width: 25%; padding: 0 10px; box-sizing: border-box; }
        .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; padding: 0 10px; box-sizing: border-box; }
        .col-md-12 { flex: 0 0 100%; max-width: 100%; padding: 0 10px; box-sizing: border-box; }
        .text-center { text-align: center; }
        .mb-3 { margin-bottom: 1rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .p-0 { padding: 0 !important; }
        
        .review-table th, .review-table td { border: 1px solid #eee; text-align: center; vertical-align: middle; }
        .review-table th { background: #f8f9fa; }
        .review-table input { text-align: center; }

        /* --- MODAL --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: flex-start; padding-top: 50px; overflow-y: auto; }
        .modal-overlay.centered { align-items: center; padding-top: 0; }
        .modal-content { background: #fff; width: 700px; border-radius: 8px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); position: relative; margin-bottom: 50px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-header h3 { font-size: 1.2rem; font-weight: 600; margin: 0; }
        .close-modal { cursor: pointer; font-size: 1.2rem; color: #aaa; }
        .close-modal:hover { color: #333; }
        .modal-body h4 { font-size: 1rem; font-weight: 600; margin-bottom: 15px; margin-top: 10px; color: #333; }
    </style>
</head>
<body>

    <?php include '../sidebars.php'; ?>

    <main class="main-content" id="mainContent">
        
        <?php if ($page == 'dashboard'): ?>
            <div class="page-header">
                <h2>Dashboard</h2>
            </div>
            <div class="card">
                <p>Welcome to the Performance Dashboard.</p>
            </div>

        <?php elseif ($page == 'indicator'): ?>
            <div class="breadcrumb">Performance > Performance Indicator</div>
            <div class="page-header">
                <h2>Performance Indicator</h2>
                <button class="btn btn-primary" onclick="openModal('addModal', null)"><i class="fa-solid fa-circle-plus"></i> Add Indicator</button>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox"></th>
                            <th>Designation</th>
                            <th>Department</th>
                            <th>Approved By</th>
                            <th>Created Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="indicatorTableBody">
                        <tr>
                            <td><input type="checkbox"></td>
                            <td class="cell-desig" style="color: #ff5b16; font-weight: 500;">Web Designer</td>
                            <td class="cell-dept">Designing</td>
                            <td>
                                <div class="profile-cell">
                                    <img src="https://ui-avatars.com/api/?name=Doglas+Martini&background=random" alt="admin">
                                    <div><strong>Doglas Martini</strong><br><small>Manager</small></div>
                                </div>
                            </td>
                            <td>14 Jan 2024</td>
                            <td><span class="status-badge">Active</span></td>
                            <td class="action-icons">
                                <i class="fa-solid fa-pencil" onclick="editRow(this)"></i> 
                                <i class="fa-solid fa-trash" onclick="deleteRow(this)"></i>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'review'): ?>
            <div class="breadcrumb">Performance > Performance Review</div>
            <div class="page-header">
                <h2>Performance Review</h2>
            </div>

            <form>
                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Employee Basic Information</h3></div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group"><label class="form-label">Name</label><input type="text" class="form-control" placeholder="Employee Name"></div>
                            <div class="form-group"><label class="form-label">Department</label><input type="text" class="form-control" placeholder="Department"></div>
                            <div class="form-group"><label class="form-label">Designation</label><input type="text" class="form-control" placeholder="Designation"></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label class="form-label">Emp ID</label><input type="text" class="form-control" placeholder="EMP-001"></div>
                            <div class="form-group"><label class="form-label">Date of Join</label><input type="date" class="form-control"></div>
                            <div class="form-group"><label class="form-label">Previous Exp</label><input type="text" class="form-control"></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label class="form-label">RO's Name</label><input type="text" class="form-control"></div>
                            <div class="form-group"><label class="form-label">RO Designation</label><input type="text" class="form-control"></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Professional Excellence</h3></div>
                    <div class="table-responsive">
                        <table class="table review-table">
                            <thead>
                                <tr>
                                    <th>#</th><th>KRA</th><th>KPI</th><th>Weight</th>
                                    <th>Self %</th><th>Self Pts</th><th>RO %</th><th>RO Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td rowspan="2">1</td><td rowspan="2">Production</td><td>Quality</td>
                                    <td><input type="text" class="form-control weight-prof" value="30" readonly></td>
                                    <td><input type="number" class="form-control self-score-prof" oninput="calcProf(this)"></td>
                                    <td><input type="text" class="form-control self-points-prof" readonly></td>
                                    <td><input type="number" class="form-control ro-score-prof" oninput="calcProf(this)"></td>
                                    <td><input type="text" class="form-control ro-points-prof" readonly></td>
                                </tr>
                                <tr>
                                    <td>TAT</td>
                                    <td><input type="text" class="form-control weight-prof" value="30" readonly></td>
                                    <td><input type="number" class="form-control self-score-prof" oninput="calcProf(this)"></td>
                                    <td><input type="text" class="form-control self-points-prof" readonly></td>
                                    <td><input type="number" class="form-control ro-score-prof" oninput="calcProf(this)"></td>
                                    <td><input type="text" class="form-control ro-points-prof" readonly></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-center">Total</td>
                                    <td><input type="text" class="form-control" value="60" readonly></td>
                                    <td></td>
                                    <td><input type="text" id="prof_self_total" class="form-control" readonly value="0"></td>
                                    <td></td>
                                    <td><input type="text" id="prof_ro_total" class="form-control" readonly value="0"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Personal Excellence</h3></div>
                    <div class="table-responsive">
                        <table class="table review-table">
                            <thead>
                                <tr>
                                    <th>#</th><th>Attribute</th><th>Indicator</th><th>Weight</th>
                                    <th>Self %</th><th>Self Pts</th><th>RO %</th><th>RO Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td><td>Attendance</td><td>Leaves</td>
                                    <td><input type="text" class="form-control weight-pers" value="20" readonly></td>
                                    <td><input type="number" class="form-control self-score-pers" oninput="calcPers(this)"></td>
                                    <td><input type="text" class="form-control self-points-pers" readonly></td>
                                    <td><input type="number" class="form-control ro-score-pers" oninput="calcPers(this)"></td>
                                    <td><input type="text" class="form-control ro-points-pers" readonly></td>
                                </tr>
                                <tr>
                                    <td>2</td><td>Attitude</td><td>Collaboration</td>
                                    <td><input type="text" class="form-control weight-pers" value="20" readonly></td>
                                    <td><input type="number" class="form-control self-score-pers" oninput="calcPers(this)"></td>
                                    <td><input type="text" class="form-control self-points-pers" readonly></td>
                                    <td><input type="number" class="form-control ro-score-pers" oninput="calcPers(this)"></td>
                                    <td><input type="text" class="form-control ro-points-pers" readonly></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-center">Total</td>
                                    <td><input type="text" class="form-control" value="40" readonly></td>
                                    <td></td>
                                    <td><input type="text" id="pers_self_total" class="form-control" readonly value="0"></td>
                                    <td></td>
                                    <td><input type="text" id="pers_ro_total" class="form-control" readonly value="0"></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-center"><b>Grand Total Score</b></td>
                                    <td colspan="5"><input type="text" id="grand_total" class="form-control text-center" readonly value="0"></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="text-center mt-3 grade-span" style="padding-top:15px;">
                            <h4>Grade Legend</h4>
                            <span class="badge bg-inverse-danger">Below 65 Poor</span> 
                            <span class="badge bg-inverse-warning">65-74 Average</span> 
                            <span class="badge bg-inverse-info">75-84 Satisfactory</span> 
                            <span class="badge bg-inverse-purple">85-92 Good</span> 
                            <span class="badge bg-inverse-success">Above 92 Excellent</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Special Initiatives, Achievements</h3></div>
                    <table class="table table-bordered review-table" id="tbl_spec">
                        <thead><tr><th>#</th><th>By Self</th><th>RO Comment</th><th>HOD Comment</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Comments on Role (Alterations)</h3></div>
                    <table class="table table-bordered review-table" id="tbl_alt">
                        <thead><tr><th>#</th><th>By Self</th><th>RO Comment</th><th>HOD Comment</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <?php 
                $sections = ["Comments on Role (Self)", "Appraisee's Strengths (RO)", "Appraisee's Strengths (HOD)"];
                foreach($sections as $sec):
                ?>
                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2"><?= $sec ?></h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>#</th><th>Strengths</th><th>Areas for Improvement</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                            <tr><td>2</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Personal Goals</h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>#</th><th>Goal Achieved (Last Year)</th><th>Goal Set (Current Year)</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                            <tr><td>2</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Personal Updates</h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>#</th><th>Last Year</th><th>Yes/No</th><th>Details</th><th>Current Year</th><th>Yes/No</th><th>Details</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>1</td><td>Married/Engaged?</td>
                                <td><select class="form-select"><option>No</option><option>Yes</option></select></td>
                                <td><input type="text" class="form-control"></td>
                                <td>Marriage Plans</td>
                                <td><select class="form-select"><option>No</option><option>Yes</option></select></td>
                                <td><input type="text" class="form-control"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Professional Goals (Last Year)</h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>#</th><th>By Self</th><th>RO Comment</th><th>HOD Comment</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Professional Goals (Forthcoming)</h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>#</th><th>By Self</th><th>RO Comment</th><th>HOD Comment</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Training Requirements</h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>#</th><th>By Self</th><th>RO Comment</th><th>HOD Comment</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">General Comments</h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>#</th><th>Self</th><th>RO</th><th>HOD</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">For RO's Use Only</h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>Question</th><th>Yes/No</th><th>If Yes - Details</th></tr></thead>
                        <tbody>
                            <tr><td>Work related Issues</td><td><select class="form-select"><option>No</option><option>Yes</option></select></td><td><input type="text" class="form-control"></td></tr>
                            <tr><td>Leave Issues</td><td><select class="form-select"><option>No</option><option>Yes</option></select></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">For HRD's Use Only</h3></div>
                    <table class="table table-bordered review-table">
                        <thead><tr><th>Parameter</th><th>Avail Pts</th><th>Pts Scored</th><th>RO Comment</th></tr></thead>
                        <tbody>
                            <tr><td>KRAs Target</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                            <tr><td>Overall Total Score</td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td><td><input type="text" class="form-control"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card mb-4">
                    <div class="card-body p-0">
                        <table class="table table-bordered review-table">
                            <thead><tr><th>Role</th><th>Name</th><th>Date</th></tr></thead>
                            <tbody>
                                <tr><td>Employee</td><td><input type="text" class="form-control"></td><td><input type="date" class="form-control"></td></tr>
                                <tr><td>RO</td><td><input type="text" class="form-control"></td><td><input type="date" class="form-control"></td></tr>
                                <tr><td>HOD</td><td><input type="text" class="form-control"></td><td><input type="date" class="form-control"></td></tr>
                                <tr><td>HRD</td><td><input type="text" class="form-control"></td><td><input type="date" class="form-control"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="text-center mb-5">
                    <button type="button" class="btn btn-primary" onclick="openMessageModal('Review Submitted Successfully!')"><i class="fa-solid fa-floppy-disk"></i> Submit Review</button>
                </div>
            </form>

        <?php endif; ?>
    </main>

    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Indicator</h3>
                <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
            </div>
            
            <div class="form-group">
                <label class="form-label">Designation</label>
                <select id="newDesignation" class="form-select">
                    <option value="">Select Designation</option>
                    <option value="Web Designer">Web Designer</option>
                    <option value="IOS Developer">IOS Developer</option>
                    <option value="Android Developer">Android Developer</option>
                    <option value="DevOps Engineer">DevOps Engineer</option>
                </select>
            </div>

            <h4>Technical</h4>
            <div class="row">
                <div class="col-md-3"><div class="form-group"><label class="form-label">Customer Experience</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Marketing</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Management</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Administration</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Presentation Skills</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Quality of Work</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Efficiency</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
            </div>

            <h4>Organizational</h4>
            <div class="row">
                <div class="col-md-3"><div class="form-group"><label class="form-label">Integrity</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Professionalism</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Team Work</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Critical Thinking</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Conflict Management</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Attendance</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label class="form-label">Ability To Meet Deadline</label><select class="form-select"><option>Select</option><option>None</option><option>Beginner</option><option>Expert</option></select></div></div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select id="newStatus" class="form-select">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button class="btn btn-dark" onclick="closeModal('addModal')">Cancel</button>
                <button class="btn btn-primary" id="saveBtn" onclick="addIndicator()">Add Indicator</button>
            </div>
        </div>
    </div>

    <div id="messageModal" class="modal-overlay centered">
        <div class="modal-content" style="width: 400px; text-align: center;">
            <div class="modal-header" style="justify-content: center; border-bottom: none;">
                <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: #39da8a; margin-bottom: 10px;"></i>
            </div>
            <h3 id="msgText" style="margin-bottom: 20px; text-align: center;">Success</h3>
            <button class="btn btn-primary" onclick="closeModal('messageModal')">OK</button>
        </div>
    </div>

    <div id="deleteConfirmModal" class="modal-overlay centered">
        <div class="modal-content" style="width: 400px; text-align: center;">
            <div class="modal-header" style="justify-content: center; border-bottom: none;">
                <i class="fa-solid fa-circle-exclamation" style="font-size: 3rem; color: #ff9b44; margin-bottom: 10px;"></i>
            </div>
            <h3 style="margin-bottom: 10px; text-align: center;">Are you sure?</h3>
            <p style="margin-bottom: 20px; color: #666;">Do you really want to delete this indicator?</p>
            <div style="display: flex; justify-content: center; gap: 10px;">
                <button class="btn btn-dark" onclick="closeModal('deleteConfirmModal')">Cancel</button>
                <button class="btn btn-primary" style="background-color: #ff5b16;" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let editingRow = null; 
        let rowToDelete = null;

        // Modal Logic
        function openModal(id, mode) { 
            const modal = document.getElementById(id);
            if (id === 'addModal') {
                const title = document.getElementById('modalTitle');
                const btn = document.getElementById('saveBtn');
                const desigInput = document.getElementById('newDesignation');
                const statusInput = document.getElementById('newStatus');

                if(mode === 'edit' && editingRow) {
                    title.innerText = "Edit Indicator";
                    btn.innerText = "Update Indicator";
                    const desig = editingRow.querySelector('.cell-desig').innerText;
                    const status = editingRow.querySelector('.status-badge').innerText;
                    desigInput.value = desig;
                    statusInput.value = status;
                } else {
                    editingRow = null;
                    title.innerText = "Add New Indicator";
                    btn.innerText = "Add Indicator";
                    desigInput.value = "";
                    statusInput.value = "Active";
                }
            }
            modal.style.display = 'flex'; 
        }

        function openMessageModal(msg) {
            document.getElementById('msgText').innerText = msg;
            document.getElementById('messageModal').style.display = 'flex';
        }

        function closeModal(id) { 
            document.getElementById(id).style.display = 'none'; 
            if(id === 'addModal') editingRow = null;
            if(id === 'deleteConfirmModal') rowToDelete = null;
        }

        function addIndicator() {
            const desig = document.getElementById('newDesignation').value;
            const status = document.getElementById('newStatus').value;
            
            if(!desig) return openMessageModal("Please select a Designation");
            
            if(editingRow) {
                editingRow.querySelector('.cell-desig').innerText = desig;
                const statusBadge = editingRow.querySelector('.status-badge');
                statusBadge.innerText = status;
                statusBadge.style.background = (status === 'Active') ? '#39da8a' : '#ff9b44';
                
                let dept = "Development"; 
                if(desig.includes("Designer")) dept = "Designing";
                if(desig.includes("Engineer")) dept = "DevOps";
                editingRow.querySelector('.cell-dept').innerText = dept;

                openMessageModal("Indicator Updated Successfully!");
            } else {
                let dept = "Development"; 
                if(desig.includes("Designer")) dept = "Designing";
                if(desig.includes("Engineer")) dept = "DevOps";

                const tbody = document.getElementById('indicatorTableBody');
                const row = `
                    <tr>
                        <td><input type="checkbox"></td>
                        <td class="cell-desig" style="color:#ff5b16;font-weight:500;">${desig}</td>
                        <td class="cell-dept">${dept}</td>
                        <td>
                            <div class="profile-cell">
                                <img src="https://ui-avatars.com/api/?name=User&background=random">
                                <div><strong>System Admin</strong><br><small>Manager</small></div>
                            </div>
                        </td>
                        <td>${new Date().toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'})}</td>
                        <td><span class="status-badge" style="background:${status=='Active'?'#39da8a':'#ff9b44'}">${status}</span></td>
                        <td class="action-icons">
                            <i class="fa-solid fa-pencil" onclick="editRow(this)"></i> 
                            <i class="fa-solid fa-trash" onclick="deleteRow(this)"></i>
                        </td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('afterbegin', row);
                openMessageModal("Indicator Added Successfully!");
            }
            closeModal('addModal');
        }

        function deleteRow(icon) {
            rowToDelete = icon.closest("tr");
            openModal('deleteConfirmModal');
        }

        function confirmDelete() {
            if (rowToDelete) {
                rowToDelete.remove();
                rowToDelete = null;
            }
            closeModal('deleteConfirmModal');
        }

        function editRow(icon) {
            editingRow = icon.closest("tr");
            openModal('addModal', 'edit');
        }

        // --- CALCULATION LOGIC ---
        function calcProf(input) {
            let row = input.closest('tr');
            let weight = parseFloat(row.querySelector('.weight-prof').value) || 0;
            let val = parseFloat(input.value) || 0;
            let points = (val * weight) / 100;
            
            if(input.classList.contains('self-score-prof')) {
                row.querySelector('.self-points-prof').value = points.toFixed(1);
                updateSum('self-points-prof', 'prof_self_total');
            } else {
                row.querySelector('.ro-points-prof').value = points.toFixed(1);
                updateSum('ro-points-prof', 'prof_ro_total');
            }
            updateGrandTotal();
        }

        function calcPers(input) {
            let row = input.closest('tr');
            let weight = parseFloat(row.querySelector('.weight-pers').value) || 0;
            let val = parseFloat(input.value) || 0;
            let points = (val * weight) / 100;
            
            if(input.classList.contains('self-score-pers')) {
                row.querySelector('.self-points-pers').value = points.toFixed(1);
                updateSum('self-points-pers', 'pers_self_total');
            } else {
                row.querySelector('.ro-points-pers').value = points.toFixed(1);
                updateSum('ro-points-pers', 'pers_ro_total');
            }
            updateGrandTotal();
        }

        function updateSum(cls, id) {
            let s=0; document.querySelectorAll('.'+cls).forEach(e=>s+=parseFloat(e.value)||0);
            document.getElementById(id).value = s.toFixed(1);
        }

        function updateGrandTotal() {
            let t = (parseFloat(document.getElementById('prof_self_total').value)||0) + 
                    (parseFloat(document.getElementById('pers_self_total').value)||0);
            document.getElementById('grand_total').value = t.toFixed(1);
        }
    </script>
</body>
</html>