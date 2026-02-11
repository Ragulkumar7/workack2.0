<?php
session_start();
// include 'db_connect.php'; 

// You can add any ticket-related logic here later
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tickets - IT Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #1b5a5a;
            --primary-light: #267a7a;
            --bg-light: #f4f6f9;
            
            --primary-sidebar-width: 95px;
            --secondary-sidebar-width: 220px;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Layout */
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
        }

        .main-content {
            flex-grow: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.35s ease;
        }

        /* Push content when secondary sidebar opens */
        body.secondary-open .d-flex-wrapper {
            margin-left: var(--secondary-sidebar-width);
        }

        body.secondary-open .main-content {
            margin-left: var(--secondary-sidebar-width);
        }

        /* Page-specific styles */
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-unassigned {
            background-color: #e2e3e5;
            color: #495057;
        }

        .btn-brand {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-brand:hover {
            background-color: var(--primary-light);
            color: white;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        @media (max-width: 992px) {
            body.secondary-open .main-content {
                margin-left: 0; /* or adjust as needed on mobile */
            }
        }
    </style>
</head>
<body>

<div class="d-flex-wrapper">

    <div class="sidebar-container">
        <?php include '../sidebars.php'; ?>
    </div>

    <div class="main-content">

        <?php include '../header.php'; ?>

        <div class="container-fluid p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h3 class="mb-0" style="color: #1b5a5a; font-weight: 700;">
                    <i class="fas fa-ticket-alt me-2"></i>Ticket Management
                </h3>
                
                <div class="d-flex gap-2">
                    <select class="form-select w-auto">
                        <option>All Categories</option>
                        <option>Internal (System Admin)</option>
                        <option>External (Vendor)</option>
                        <option>Network</option>
                        <option>Hardware</option>
                        <option>Software</option>
                    </select>
                    
                    <select class="form-select w-auto">
                        <option>All Statuses</option>
                        <option>Open</option>
                        <option>In Progress</option>
                        <option>Waiting on User</option>
                        <option>Resolved</option>
                        <option>Closed</option>
                    </select>

                    <button class="btn btn-brand btn-sm">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Ticket ID</th>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Assigned To</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4 fw-bold">#TKT-99201</td>
                                    <td>11 Feb 2026</td>
                                    <td>Printer Config Issue - Sales Floor</td>
                                    <td>Internal</td>
                                    <td>
                                        <span class="badge badge-unassigned">Unassigned</span>
                                    </td>
                                    <td><span class="badge bg-warning text-dark">Medium</span></td>
                                    <td><span class="badge bg-primary">Open</span></td>
                                    <td class="text-end pe-4">
                                        <a href="view_ticket_details.php?id=99201" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="#" class="btn btn-sm btn-brand">
                                            <i class="fas fa-tasks"></i> Manage
                                        </a>
                                    </td>
                                </tr>

                                <!-- Example additional rows -->
                                <tr>
                                    <td class="ps-4 fw-bold">#TKT-86050</td>
                                    <td>10 Feb 2026</td>
                                    <td>Server Down in Sales Department</td>
                                    <td>Internal</td>
                                    <td>
                                        <span class="badge bg-success">You</span>
                                    </td>
                                    <td><span class="badge bg-danger">Critical</span></td>
                                    <td><span class="badge bg-warning text-dark">In Progress</span></td>
                                    <td class="text-end pe-4">
                                        <a href="view_ticket_details.php?id=86050" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="#" class="btn btn-sm btn-brand">
                                            <i class="fas fa-tasks"></i> Manage
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="ps-4 fw-bold">#TKT-78123</td>
                                    <td>09 Feb 2026</td>
                                    <td>Internet Connectivity - 3rd Floor</td>
                                    <td>External (Vendor)</td>
                                    <td>
                                        <span class="badge bg-info">Vendor Team</span>
                                    </td>
                                    <td><span class="badge bg-warning text-dark">Medium</span></td>
                                    <td><span class="badge bg-secondary">Waiting</span></td>
                                    <td class="text-end pe-4">
                                        <a href="#" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="#" class="btn btn-sm btn-brand">
                                            <i class="fas fa-tasks"></i> Manage
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-muted text-center py-3">
                    Showing 1–10 of 48 tickets • <a href="#" class="text-decoration-none">View all tickets</a>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Make sure these functions exist (usually in sidebars.php) -->
<script>
// These should be in sidebars.php — duplicated here just in case
function closeSubMenu() {
    const panel = document.getElementById('secondaryPanel');
    if (panel) panel.classList.remove('open');
    document.body.classList.remove('secondary-open');
}

function handleNavClick(item, element) {
    const panel = document.getElementById('secondaryPanel');
    const container = document.getElementById('subItemContainer');

    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');

    if (item.subItems && item.subItems.length > 0) {
        panel.classList.add('open');
        document.body.classList.add('secondary-open');
        // ... your submenu population logic ...
    } else {
        closeSubMenu();
        if (item.path && item.path !== '#') window.location.href = item.path;
    }
}
</script>

</body>
</html>