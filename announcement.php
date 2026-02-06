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
    <title>SmartHR | Announcements</title>
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
        }

        body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; margin: 0; overflow-x: hidden; }
        
        /* --- SIDEBAR INTEGRATION CSS --- */
        #mainContent { 
            margin-left: 95px; /* Primary Sidebar Width */
            padding: 30px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
        }
        #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }
        /* --------------------------- */

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 600; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; }

        /* Tabs Navigation */
        .top-settings-nav { display: flex; background: var(--white); padding: 0 15px; border-radius: 8px; border: 1px solid var(--border-light); margin-bottom: 30px; }
        .top-nav-item { padding: 15px 20px; text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 500; border-bottom: 3px solid transparent; cursor: pointer; }
        .top-nav-item.active { color: var(--primary-orange); border-bottom: 3px solid var(--primary-orange); }

        /* Content Cards */
        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; padding: 0; overflow: hidden; display: none; }
        .content-card.active { display: block; }
        .card-title { font-size: 18px; font-weight: 600; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }

        .notif-table { width: 100%; border-collapse: collapse; }
        .notif-table th { text-align: left; padding: 15px 25px; font-size: 13px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #fafafa; }
        .notif-table td { padding: 15px 25px; border-bottom: 1px solid var(--border-light); font-size: 14px; }
        
        .badge { padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-urgent { background: #fff1f0; color: #f5222d; border: 1px solid #ffa39e; }
        .badge-info { background: #e6f7ff; color: #1890ff; border: 1px solid #91d5ff; }

        .btn-save { background: var(--primary-orange); color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .action-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px; margin-left: 10px; }
        .action-btn:hover { color: var(--primary-orange); }

        /* Modals */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; padding: 30px; border-radius: 8px; width: 600px; position: relative; }
        .modal-header { margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { margin-bottom: 15px; }
        label { display: block; font-size: 14px; margin-bottom: 8px; font-weight: 500; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid var(--border-light); border-radius: 6px; font-size: 14px; }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>

    <div id="mainContent">
        <div class="page-header">
            <div>
                <h1>Announcements</h1>
                <div class="breadcrumb" id="breadcrumb-path">Dashboard / HRM / All Announcements</div>
            </div>
            <button class="btn-save" onclick="openModal('addAnnouncementModal')">
                <i class="fas fa-plus"></i> Add New
            </button>
        </div>

        <div class="top-settings-nav">
            <div class="top-nav-item active" onclick="switchTab('all', this)"><i class="fas fa-bullhorn"></i> All Announcements</div>
            <div class="top-nav-item" onclick="switchTab('scheduled', this)"><i class="fas fa-clock"></i> Scheduled</div>
            <div class="top-nav-item" onclick="switchTab('archived', this)"><i class="fas fa-archive"></i> Archived</div>
        </div>

        <div id="all-card" class="content-card active">
            <div class="card-title">All Announcements</div>
            <table class="notif-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Posted Date</th>
                        <th>Priority</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div style="font-weight:600;">Office Holiday: Pongal 2026</div>
                            <div style="font-size:12px; color:var(--text-muted);">Closed from Jan 14 to Jan 16...</div>
                        </td>
                        <td><span class="badge badge-info">Holiday</span></td>
                        <td>06 Feb 2026</td>
                        <td><span class="badge badge-urgent">High</span></td>
                        <td style="text-align: right;">
                            <button class="action-btn" onclick="openModal('editAnnouncementModal')"><i class="fas fa-edit"></i></button>
                            <button class="action-btn" style="color:#ff4d4f;" onclick="openModal('deleteModal')"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="scheduled-card" class="content-card">
            <div class="card-title">Scheduled Postings</div>
            <table class="notif-table">
                <thead><tr><th>Subject</th><th>Schedule Date</th><th>Status</th><th style="text-align: right;">Action</th></tr></thead>
                <tbody>
                    <tr>
                        <td><strong>Team Outing Update</strong></td>
                        <td>15 Feb 2026</td>
                        <td><span class="badge" style="background:#fff7e6; color:#fa8c16;">Pending</span></td>
                        <td style="text-align: right;">
                            <button class="action-btn"><i class="fas fa-edit"></i></button>
                            <button class="action-btn" style="color:#ff4d4f;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="archived-card" class="content-card">
            <div class="card-title">Archived Announcements</div>
            <table class="notif-table">
                <thead><tr><th>Subject</th><th>Expiry Date</th><th>Priority</th><th style="text-align: right;">Action</th></tr></thead>
                <tbody>
                    <tr>
                        <td>2025 Year End Review</td>
                        <td>31 Dec 2025</td>
                        <td>Low</td>
                        <td style="text-align: right;">
                            <button class="action-btn"><i class="fas fa-eye"></i></button>
                            <button class="action-btn" style="color:#ff4d4f;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addAnnouncementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Add New Announcement</h3><span style="cursor:pointer;" onclick="closeModal('addAnnouncementModal')">&times;</span></div>
            <form>
                <div class="input-group"><label>Subject Title</label><input type="text" placeholder="Enter title" required></div>
                <div class="form-grid">
                    <div class="input-group"><label>Category</label><select><option>General</option><option>Holiday</option><option>Policy</option></select></div>
                    <div class="input-group"><label>Priority</label><select><option>Low</option><option>Medium</option><option>High</option></select></div>
                </div>
                <div class="input-group"><label>Publish Date</label><input type="date" required></div>
                <div class="input-group"><label>Message Details</label><textarea rows="4" placeholder="Description..." required></textarea></div>
                <div style="text-align:right;"><button type="submit" class="btn-save">Post Announcement</button></div>
            </form>
        </div>
    </div>

    <div id="editAnnouncementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Announcement</h3><span style="cursor:pointer;" onclick="closeModal('editAnnouncementModal')">&times;</span></div>
            <form>
                <div class="input-group"><label>Subject Title</label><input type="text" value="Office Holiday: Pongal 2026"></div>
                <div style="text-align:right;"><button type="submit" class="btn-save">Save Changes</button></div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content" style="width:400px; text-align:center;">
            <i class="fas fa-exclamation-triangle fa-3x" style="color:#ff4d4f; margin-bottom:15px;"></i>
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to remove this announcement permanently?</p>
            <div style="margin-top:20px;">
                <button class="btn-save" style="background:#eee; color:#333; margin-right:10px;" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn-save" style="background:#ff4d4f;">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function switchTab(tabId, element) {
            document.querySelectorAll('.content-card').forEach(card => card.classList.remove('active'));
            document.querySelectorAll('.top-nav-item').forEach(item => item.classList.remove('active'));
            document.getElementById(tabId + '-card').classList.add('active');
            element.classList.add('active');
            document.getElementById('breadcrumb-path').innerText = 'Dashboard / HRM / ' + element.innerText;
        }

        window.onclick = function(event) { if (event.target.className === 'modal') { event.target.style.display = "none"; } }
    </script>
</body>
</html>