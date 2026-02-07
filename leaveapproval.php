<?php
// Handle page view routing
$page = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR - HRMS</title>
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
            --status-open-bg: #e3f2fd; --status-open-text: #1976d2;
            --status-solved-bg: #e8f5e9; --status-solved-text: #2e7d32;
            --status-pending-bg: #fff3e0; --status-pending-text: #ef6c00;
            --status-rej-bg: #ffebee; --status-rej-text: #c62828;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: var(--bg-body); display: flex; height: 100vh; overflow: hidden; color: var(--text-primary); }

        /* ==============================
           SIDEBAR STYLING
           ============================== */
        .sidebar { width: 260px; background-color: var(--sidebar-bg); border-right: 1px solid #eee; display: flex; flex-direction: column; z-index: 100; flex-shrink: 0; }
        .sidebar-brand { padding: 20px; display: flex; align-items: center; gap: 12px; }
        .sidebar-brand img { width: 35px; }
        .sidebar-brand span { font-size: 1.4rem; font-weight: 700; color: #333; }
        
        .welcome-text { padding: 10px 20px; font-size: 0.9rem; font-weight: 600; color: #1f2d3d; }
        .user-profile-card { margin: 10px 20px 20px; padding: 20px; background: #f8f9fa; border-radius: 12px; text-align: center; }
        .user-profile-card img { width: 60px; height: 60px; border-radius: 50%; margin-bottom: 10px; border: 3px solid #fff; }
        .user-profile-card h4 { font-size: 0.95rem; color: #333; margin-bottom: 2px; }
        .user-profile-card span { font-size: 0.8rem; color: #888; }

        .nav-menu { list-style: none; flex: 1; overflow-y: auto; padding: 0 10px; }
        .menu-label { padding: 10px 15px; font-size: 0.75rem; text-transform: uppercase; color: #999; font-weight: 600; }
        .nav-link { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; color: var(--sidebar-text); text-decoration: none; font-size: 0.9rem; border-radius: 8px; transition: 0.3s; cursor: pointer; }
        .nav-link:hover { color: var(--accent-color); background-color: #fff4f0; }
        .nav-link.active { background-color: #f1f1f1; color: var(--accent-color); font-weight: 500; }
        .nav-icon { width: 25px; margin-right: 10px; text-align: center; }

        .submenu { list-style: none; max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; }
        .submenu.open { max-height: 500px; }
        .submenu .nav-link { padding-left: 50px; font-size: 0.85rem; }
        .rotate-arrow { transition: transform 0.3s; font-size: 0.7rem; }
        .rotate-arrow.rotated { transform: rotate(180deg); }

        /* --- MAIN CONTENT --- */
        .main-content { flex: 1; padding: 25px; overflow-y: auto; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .breadcrumb { font-size: 0.8rem; color: #888; margin-bottom: 5px; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.9rem; color: white; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background-color: var(--accent-color); }
        .btn-primary:hover { background-color: #e64a0f; }
        .btn-dark { background-color: #333; color: #fff; }
        .btn-sm { padding: 4px 8px; font-size: 0.8rem; }
        .btn-success-soft { background: #e8f5e9; color: #2e7d32; }
        .btn-danger-soft { background: #ffebee; color: #c62828; }

        /* Cards & Tables */
        .card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 25px; border: 1px solid #eee; box-shadow: 0 2px 6px rgba(0,0,0,0.02); width: 100%; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; color: #333; font-weight: 600; border-bottom: 2px solid #eee; font-size: 0.85rem; }
        td { padding: 15px 12px; border-bottom: 1px solid #f9f9f9; font-size: 0.85rem; color: #555; vertical-align: middle; }
        
        /* Stats Grid */
        .stats-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border-left: 4px solid transparent; }
        .stat-info h3 { font-size: 1.8rem; font-weight: 600; color: #333; margin-bottom: 5px; }
        .stat-info p { color: #777; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }

        /* Form Elements */
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-size: 0.85rem; font-weight: 500; color: #333; }
        .form-control, .form-select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; font-size: 0.9rem; background-color: #fff; }
        .form-control:focus { border-color: var(--accent-color); }
        
        /* Badges & Specifics */
        .profile-cell { display: flex; align-items: center; gap: 10px; }
        .profile-cell img { width: 30px; height: 30px; border-radius: 50%; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; display: inline-block; }
        .badge-pending { background: var(--status-pending-bg); color: var(--status-pending-text); }
        .badge-approved { background: var(--status-solved-bg); color: var(--status-solved-text); }
        .badge-rejected { background: var(--status-rej-bg); color: var(--status-rej-text); }
        
        .action-icons i { margin-left: 10px; cursor: pointer; color: #888; }
        .action-icons i:hover { color: var(--accent-color); }

        /* --- MODAL --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: flex-start; padding-top: 50px; overflow-y: auto; }
        .modal-overlay.centered { align-items: center; padding-top: 0; }
        .modal-content { background: #fff; width: 700px; border-radius: 8px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); position: relative; margin-bottom: 50px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-header h3 { font-size: 1.2rem; font-weight: 600; margin: 0; }
        .close-modal { cursor: pointer; font-size: 1.2rem; color: #aaa; }
        .close-modal:hover { color: #333; }
        
        /* Review Table Specifics */
        .review-table th, .review-table td { border: 1px solid #eee; text-align: center; vertical-align: middle; }
        .review-table th { background: #f8f9fa; }
        .row { display: flex; flex-wrap: wrap; margin: 0 -10px; }
        .col-md-3, .col-md-4, .col-md-6, .col-md-12 { padding: 0 10px; box-sizing: border-box; }
        .col-md-3 { width: 25%; } .col-md-4 { width: 33.33%; } .col-md-6 { width: 50%; } .col-md-12 { width: 100%; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="https://smarthr.dreamstechnologies.com/html/template/assets/img/logo.png" alt="Logo">
            <span>SmartHR</span>
        </div>
        <div class="welcome-text">Welcome to SmartHR</div>
        <div class="user-profile-card">
            <img src="https://smarthr.dreamstechnologies.com/html/template/assets/img/profiles/avatar-02.jpg" alt="User">
            <h4>Adrian Herman</h4>
            <span>System Admin</span>
        </div>
        <div class="menu-label">HRM</div>
        <ul class="nav-menu">
            <li><a href="?view=dashboard" class="nav-link"><i class="fa-solid fa-gauge nav-icon"></i> Dashboard</a></li>
            <li><a href="#" class="nav-link"><i class="fa-solid fa-users nav-icon"></i> Employees</a></li>
            
            <li>
                <a href="#" onclick="toggleSubmenu(event)" class="nav-link <?php echo $page=='leave-approval'?'active':'';?>">
                    <span><i class="fa-solid fa-calendar-days nav-icon"></i> Leaves</span>
                    <i class="fa-solid fa-chevron-down rotate-arrow <?php echo $page=='leave-approval'?'rotated':'';?>"></i>
                </a>
                <ul class="submenu <?php echo $page=='leave-approval'?'open':'';?>">
                    <li><a href="?view=leave-approval" class="nav-link <?php echo $page=='leave-approval'?'active':'';?>">Leave Approval</a></li>
                </ul>
            </li>

            

            <li><a href="?view=tickets" class="nav-link"><i class="fa-solid fa-ticket nav-icon"></i> Tickets</a></li>
        </ul>
    </aside>

    <main class="main-content">
        
        <?php if ($page == 'dashboard'): ?>
            <div class="page-header"><h2>Dashboard</h2></div>
            <div class="card"><p>Welcome to the Dashboard.</p></div>

        <?php elseif ($page == 'leave-approval'): ?>
            <div class="breadcrumb">Leaves > Leave Approval</div>
            
            

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>No of Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leaveTableBody">
                        <tr>
                            <td>
                                <div class="profile-cell">
                                    <img src="https://i.pravatar.cc/150?u=5" alt="user">
                                    <div><strong>John Doe</strong><br><small>Web Designer</small></div>
                                </div>
                            </td>
                            <td>Medical Leave</td>
                            <td>27 Feb 2024</td>
                            <td>27 Feb 2024</td>
                            <td>1 day</td>
                            <td>Going to Hospital</td>
                            <td><span class="status-badge badge-pending">Pending</span></td>
                            <td>
                                <button class="btn btn-sm btn-success-soft" onclick="approveLeave(this)" title="Approve"><i class="fa-solid fa-check"></i></button>
                                <button class="btn btn-sm btn-danger-soft" onclick="rejectLeave(this)" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="profile-cell">
                                    <img src="https://i.pravatar.cc/150?u=8" alt="user">
                                    <div><strong>Sarah Smith</strong><br><small>Android Dev</small></div>
                                </div>
                            </td>
                            <td>Casual Leave</td>
                            <td>15 Jan 2024</td>
                            <td>16 Jan 2024</td>
                            <td>2 days</td>
                            <td>Personal Work</td>
                            <td><span class="status-badge badge-approved">Approved</span></td>
                            <td>
                                </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'performance-indicator'): ?>
            <div class="breadcrumb">Performance > Performance Indicator</div>
            <div class="page-header">
                <h2>Performance Indicator</h2>
                <button class="btn btn-primary" onclick="openModal('addModal', null)"><i class="fa-solid fa-circle-plus"></i> Add Indicator</button>
            </div>
            <div class="card">
                <table>
                    <thead>
                        <tr><th><input type="checkbox"></th><th>Designation</th><th>Department</th><th>Approved By</th><th>Created Date</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody id="indicatorTableBody">
                        <tr>
                            <td><input type="checkbox"></td>
                            <td class="cell-desig" style="color: #ff5b16; font-weight: 500;">Web Designer</td>
                            <td class="cell-dept">Designing</td>
                            <td><div class="profile-cell"><img src="https://i.pravatar.cc/150?u=1"><div><strong>Doglas Martini</strong><br><small>Manager</small></div></div></td>
                            <td>14 Jan 2024</td>
                            <td><span class="status-badge" style="background:#39da8a;color:#fff;">Active</span></td>
                            <td class="action-icons"><i class="fa-solid fa-pencil" onclick="editRow(this)"></i> <i class="fa-solid fa-trash" onclick="deleteRow(this)"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'performance-review'): ?>
            <div class="breadcrumb">Performance > Performance Review</div>
            <div class="page-header"><h2>Performance Review</h2></div>
            <form>
                <div class="card">
                    <div class="card-header border-bottom-0 text-center"><h3 class="mb-2">Employee Basic Information</h3></div>
                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label class="form-label">Name</label><input type="text" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-label">Emp ID</label><input type="text" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-label">RO Name</label><input type="text" class="form-control"></div></div>
                    </div>
                </div>
                <div class="text-center mb-5"><button type="button" class="btn btn-primary" onclick="openMessageModal('Review Submitted Successfully!')">Submit Review</button></div>
            </form>
        <?php endif; ?>
    </main>

    <div id="addLeaveModal" class="modal-overlay">
        
            

    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h3 id="modalTitle">Add New Indicator</h3><span class="close-modal" onclick="closeModal('addModal')">&times;</span></div>
            <div class="form-group">
                <label class="form-label">Designation</label>
                <select id="newDesignation" class="form-select">
                    <option value="">Select Designation</option>
                    <option value="Web Designer">Web Designer</option>
                    <option value="IOS Developer">IOS Developer</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Status</label><select id="newStatus" class="form-select"><option value="Active">Active</option></select></div>
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
            <p style="margin-bottom: 20px; color: #666;">Do you really want to delete this item?</p>
            <div style="display: flex; justify-content: center; gap: 10px;">
                <button class="btn btn-dark" onclick="closeModal('deleteConfirmModal')">Cancel</button>
                <button class="btn btn-primary" style="background-color: #ff5b16;" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let editingRow = null; 
        let rowToDelete = null;

        function toggleSubmenu(e) {
            e.preventDefault();
            e.currentTarget.nextElementSibling.classList.toggle('open');
            e.currentTarget.querySelector('.rotate-arrow').classList.toggle('rotated');
        }

        function openModal(id, mode) { 
            const modal = document.getElementById(id);
            if (id === 'addModal') {
                const title = document.getElementById('modalTitle');
                const btn = document.getElementById('saveBtn');
                if(mode === 'edit' && editingRow) {
                    title.innerText = "Edit Indicator"; btn.innerText = "Update Indicator";
                    document.getElementById('newDesignation').value = editingRow.querySelector('.cell-desig').innerText;
                } else {
                    editingRow = null; title.innerText = "Add New Indicator"; btn.innerText = "Add Indicator";
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

        // --- LEAVE APPROVAL LOGIC ---
        function submitLeave() {
            const type = document.getElementById('leaveType').value;
            const from = document.getElementById('leaveFrom').value;
            const to = document.getElementById('leaveTo').value;
            const days = document.getElementById('leaveDays').value;
            const reason = document.getElementById('leaveReason').value;

            if(!days || !reason) return openMessageModal("Please fill all fields");

            const tbody = document.getElementById('leaveTableBody');
            const row = `
                <tr>
                    <td><div class="profile-cell"><img src="https://i.pravatar.cc/150?u=9"><div><strong>Adrian Herman</strong><br><small>System Admin</small></div></div></td>
                    <td>${type}</td>
                    <td>${from}</td>
                    <td>${to}</td>
                    <td>${days} days</td>
                    <td>${reason}</td>
                    <td><span class="status-badge badge-pending">Pending</span></td>
                    <td>
                        <button class="btn btn-sm btn-success-soft" onclick="approveLeave(this)"><i class="fa-solid fa-check"></i></button>
                        <button class="btn btn-sm btn-danger-soft" onclick="rejectLeave(this)"><i class="fa-solid fa-xmark"></i></button>
                    </td>
                </tr>
            `;
            tbody.insertAdjacentHTML('afterbegin', row);
            closeModal('addLeaveModal');
            openMessageModal("Leave Request Sent to Manager!");
        }

        function approveLeave(btn) {
            const row = btn.closest('tr');
            const badge = row.querySelector('.status-badge');
            badge.className = "status-badge badge-approved";
            badge.innerText = "Approved";
            row.cells[7].innerHTML = "";
            openMessageModal("Leave Approved. Notification sent to Employee.");
        }

        function rejectLeave(btn) {
            const row = btn.closest('tr');
            const badge = row.querySelector('.status-badge');
            badge.className = "status-badge badge-rejected";
            badge.innerText = "Rejected";
            row.cells[7].innerHTML = "";
            openMessageModal("Leave Rejected.");
        }

        // --- INDICATOR LOGIC ---
        function addIndicator() {
            const desig = document.getElementById('newDesignation').value;
            if(!desig) return openMessageModal("Select Designation");
            
            if(editingRow) {
                editingRow.querySelector('.cell-desig').innerText = desig;
                openMessageModal("Indicator Updated!");
            } else {
                const tbody = document.getElementById('indicatorTableBody');
                const row = `<tr><td><input type="checkbox"></td><td class="cell-desig" style="color:#ff5b16;font-weight:500;">${desig}</td><td>Development</td><td><div class="profile-cell"><img src="https://i.pravatar.cc/150?u=9"><div><strong>Adrian</strong><br><small>Admin</small></div></div></td><td>${new Date().toLocaleDateString()}</td><td><span class="status-badge" style="background:#39da8a;color:#fff;">Active</span></td><td class="action-icons"><i class="fa-solid fa-pencil" onclick="editRow(this)"></i> <i class="fa-solid fa-trash" onclick="deleteRow(this)"></i></td></tr>`;
                tbody.insertAdjacentHTML('afterbegin', row);
                openMessageModal("Indicator Added!");
            }
            closeModal('addModal');
        }

        function deleteRow(icon) { rowToDelete = icon.closest("tr"); openModal('deleteConfirmModal'); }
        function confirmDelete() { if(rowToDelete) rowToDelete.remove(); closeModal('deleteConfirmModal'); }
        function editRow(icon) { editingRow = icon.closest("tr"); openModal('addModal', 'edit'); }
    </script>
</body>
</html>