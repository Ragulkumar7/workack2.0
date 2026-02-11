<?php
// Session check and DB connection
session_start();
// include 'db_connect.php'; 

// Mock data
$pending_tickets = 12;
$internal_tickets = 5;
$external_tickets = 7;
$resolved_today = 3;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Admin Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1b5a5a;
            --primary-light: #267a7a;
            --bg-light: #f4f6f9;
            
            --primary-sidebar-width: 95px;
            --secondary-sidebar-width: 220px;
            --total-sidebar-width-when-open: calc(var(--primary-sidebar-width) + var(--secondary-sidebar-width));
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* ────────────────────────────────────────────────
           LAYOUT STRUCTURE
        ──────────────────────────────────────────────── */
        .d-flex-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            transition: margin-left 0.35s ease;
        }

        .sidebar-container {
            width: var(--primary-sidebar-width);
            flex-shrink: 0;
            min-height: 100vh;
            background: #fff;
            border-right: 1px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .main-content {
            flex-grow: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.35s ease;
        }

        /* When secondary sidebar is open → push main content */
        body.secondary-open .d-flex-wrapper {
            margin-left: var(--secondary-sidebar-width);
        }

        body.secondary-open .main-content {
            margin-left: var(--secondary-sidebar-width);
        }

        /* ────────────────────────────────────────────────
           DASHBOARD STYLES
        ──────────────────────────────────────────────── */
        .card-custom {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .card-custom:hover {
            transform: translateY(-5px);
        }
        
        .bg-brand-primary { background-color: var(--primary-color) !important; color: white; }
        .text-brand { color: var(--primary-color) !important; }
        .btn-brand {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        .btn-brand:hover {
            background-color: var(--primary-light);
            color: white;
        }

        .badge-internal { 
            background-color: #e2e6ea; 
            color: #1b5a5a; 
            border: 1px solid #1b5a5a; 
        }
        .badge-critical { 
            background-color: #ffebee; 
            color: #c62828; 
            border: 1px solid #c62828; 
        }
        
        .icon-circle {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Responsive adjustment — optional */
        @media (max-width: 992px) {
            :root {
                --secondary-sidebar-width: 240px; /* slightly wider on mobile feel */
            }
            body.secondary-open .main-content {
                margin-left: 0; /* or smaller value if you want overlay instead */
            }
        }
    </style>
</head>
<body>

<div class="d-flex-wrapper">

    <div class="sidebar-container">
        <?php include '../sidebars.php'; ?>
    </div>

    <div class="main-content" id="mainContent">
        
        <?php include '../header.php'; ?>

        <div class="container-fluid p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 style="color: #1b5a5a; font-weight: 700;">
                    <i class="fas fa-tachometer-alt me-2"></i>IT Admin Dashboard
                </h3>
                <span class="text-muted">Overview of IT Operations</span>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card card-custom h-100 border-start border-4 border-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase mb-1">Pending Tickets</h6>
                                    <h2 class="mb-0 fw-bold text-danger"><?php echo $pending_tickets; ?></h2>
                                </div>
                                <div class="icon-circle bg-danger bg-opacity-10 text-danger rounded-circle">
                                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card card-custom h-100 border-start border-4" style="border-color: #1b5a5a;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase mb-1">Internal (SysAdmin)</h6>
                                    <h2 class="mb-0 fw-bold" style="color: #1b5a5a;"><?php echo $internal_tickets; ?></h2>
                                </div>
                                <div class="icon-circle bg-light rounded-circle" style="color: #1b5a5a;">
                                    <i class="fas fa-server fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card card-custom h-100 border-start border-4 border-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase mb-1">External (Vendor)</h6>
                                    <h2 class="mb-0 fw-bold text-info"><?php echo $external_tickets; ?></h2>
                                </div>
                                <div class="icon-circle bg-info bg-opacity-10 text-info rounded-circle">
                                    <i class="fas fa-network-wired fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card card-custom h-100 border-start border-4 border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase mb-1">Resolved Today</h6>
                                    <h2 class="mb-0 fw-bold text-success"><?php echo $resolved_today; ?></h2>
                                </div>
                                <div class="icon-circle bg-success bg-opacity-10 text-success rounded-circle">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-custom shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: #1b5a5a;">
                        <i class="fas fa-list-ul me-2"></i>Critical Attention Required
                    </h5>
                    <a href="manage_tickets.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background-color: #f8f9fa;">
                                <tr>
                                    <th class="ps-4">Ticket ID</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Raised By</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4 fw-bold">#TKT-86050</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold">Server Down in Sales</span>
                                            <small class="text-muted">Created: 2 hrs ago</small>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-internal">Internal Team</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-secondary text-white rounded-circle me-2 d-flex justify-content-center align-items-center" style="width:30px; height:30px; font-size:12px;">R</div>
                                            <span class="ms-2">Rajesh (CEO)</span>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-critical">Critical</span></td>
                                    <td class="text-end pe-4">
                                        <a href="view_ticket_details.php?id=86050" class="btn btn-sm btn-brand">
                                            Manage <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Important: sidebars.php must contain the secondary sidebar markup -->
<!-- Make sure ../sidebars.php has: -->
<!-- <aside class="sidebar-secondary" id="secondaryPanel"> ... -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Make sure this script exists in sidebars.php or move it here -->
<script>
// This part should already be in your sidebars.php — just confirming
function closeSubMenu() {
    const panel = document.getElementById('secondaryPanel');
    if (panel) panel.classList.remove('open');
    document.body.classList.remove('secondary-open');
}

// If your handleNavClick is in sidebars.php, update it like this:
function handleNavClick(item, element) {
    const panel = document.getElementById('secondaryPanel');
    const container = document.getElementById('subItemContainer');

    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');

    if (item.subItems && item.subItems.length > 0) {
        panel.classList.add('open');
        document.body.classList.add('secondary-open');     // ← This line is critical
        // ... rest of your submenu population code ...
    } else {
        closeSubMenu();
        if (item.path && item.path !== '#') window.location.href = item.path;
    }
}
</script>

</body>
</html>