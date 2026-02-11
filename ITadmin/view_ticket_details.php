<?php
session_start();
// include 'db_connect.php'; 

// For demo - in real system you would fetch from DB
$ticket_id = $_GET['id'] ?? '86050';
$ticket = [
    'id'          => $ticket_id,
    'subject'     => 'Laptop Screen Flickering',
    'category'    => 'Internal (System Admin)',
    'priority'    => 'High',
    'status'      => 'Open',
    'raised_by'   => 'Vasanth (Employee)',
    'created_at'  => '11 Feb 2026 10:00 AM',
    'description' => 'The screen flickers whenever I open Android Studio. It stops when I restart, but comes back after 10 minutes.',
    'attachment'  => 'Screenshot.png',
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= htmlspecialchars($ticket['id']) ?> - Details</title>
    
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

        /* Push when secondary sidebar is open */
        body.secondary-open .d-flex-wrapper,
        body.secondary-open .main-content {
            margin-left: var(--secondary-sidebar-width);
        }

        .ticket-header {
            background: #1b5a5a;
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .ticket-info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #dee2e6;
        }

        .admin-action-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 20px;
        }

        .activity-log .alert {
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .btn-brand {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-brand:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }

        @media (max-width: 992px) {
            body.secondary-open .main-content {
                margin-left: 0;
            }
            .admin-action-box {
                position: static !important;
                margin-top: 1.5rem;
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
                    <i class="fas fa-ticket-alt me-2"></i>
                    Ticket #<?= htmlspecialchars($ticket['id']) ?>
                </h3>
                <a href="manage_tickets.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <div class="row">

                <!-- Left Column - Ticket Info + History -->
                <div class="col-lg-8">

                    <!-- Ticket Details Card -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header ticket-header d-flex justify-content-between align-items-center">
                            <span><strong>Ticket #<?= htmlspecialchars($ticket['id']) ?></strong></span>
                            <span>Raised by: <?= htmlspecialchars($ticket['raised_by']) ?></span>
                        </div>
                        <div class="card-body">
                            <h4 class="mb-3"><?= htmlspecialchars($ticket['subject']) ?></h4>

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <strong>Category:</strong><br>
                                    <?= htmlspecialchars($ticket['category']) ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Priority:</strong><br>
                                    <span class="<?= $ticket['priority'] === 'High' || $ticket['priority'] === 'Critical' ? 'text-danger fw-bold' : '' ?>">
                                        <?= htmlspecialchars($ticket['priority']) ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Current Status:</strong><br>
                                    <span class="badge bg-primary fs-6 px-3 py-2">
                                        <?= htmlspecialchars($ticket['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="ticket-info-box mb-4">
                                <label class="text-muted small fw-semibold">Detailed Description</label>
                                <p class="mt-2 mb-0">
                                    <?= nl2br(htmlspecialchars($ticket['description'])) ?>
                                </p>
                            </div>

                            <div>
                                <label class="text-muted small fw-semibold">Attachments</label>
                                <div class="mt-2">
                                    <a href="#" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-file-image me-1"></i>
                                        <?= htmlspecialchars($ticket['attachment']) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <i class="fas fa-history me-2"></i>Activity Log
                        </div>
                        <div class="card-body activity-log">
                            <div class="alert alert-secondary mb-2">
                                <small><strong>User:</strong> Ticket Created - <?= $ticket['created_at'] ?></small>
                            </div>
                            <!-- More activities would appear here dynamically -->
                            <div class="alert alert-light text-muted text-center py-3">
                                No further updates yet
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column - Admin Actions -->
                <div class="col-lg-4">
                    <div class="admin-action-box sticky-top" style="top: 20px;">
                        <h5 class="text-primary mb-4">
                            <i class="fas fa-tools me-2"></i>IT Admin Actions
                        </h5>

                        <form action="update_ticket_status.php" method="POST">
                            <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticket['id']) ?>">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Assign Engineer</label>
                                <select class="form-select" name="assigned_to">
                                    <option value="">-- Select Executive --</option>
                                    <optgroup label="Internal Team">
                                        <option value="user_1">John (System Admin)</option>
                                        <option value="user_2">Mike (Network Engineer)</option>
                                        <option value="user_3">Priya (Hardware Support)</option>
                                    </optgroup>
                                    <optgroup label="External / Vendor">
                                        <option value="vendor_1">ABC Tech Vendor</option>
                                    </optgroup>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Update Status</label>
                                <select class="form-select" name="status">
                                    <option value="Open" <?= $ticket['status']==='Open' ? 'selected' : '' ?>>Open</option>
                                    <option value="Assigned">Assigned</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Pending Parts">Pending Parts (External)</option>
                                    <option value="Resolved">Resolved</option>
                                    <option value="Closed">Closed</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Internal Admin Note <small class="text-muted">(not visible to user)</small></label>
                                <textarea class="form-control" name="admin_note" rows="4" 
                                    placeholder="e.g., Needs display replacement → checked warranty status..."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-brand btn-lg">
                                    <i class="fas fa-save me-2"></i> Update Ticket
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar secondary panel control (should be in sidebars.php, duplicated here as fallback) -->
<script>
function closeSubMenu() {
    const panel = document.getElementById('secondaryPanel');
    if (panel) panel.classList.remove('open');
    document.body.classList.remove('secondary-open');
}
</script>

</body>
</html>