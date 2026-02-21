<?php
// view_ticket_details.php

// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 2. DATABASE CONNECTION & CONFIG (Smart Path Resolver)
date_default_timezone_set('Asia/Kolkata');
$dbPath = 'include/db_connect.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    die("Critical Error: Cannot find database connection file.");
}

// 3. SILENT SCHEMA UPDATE (Ensure admin columns exist)
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS assigned_to INT DEFAULT NULL");
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS admin_note TEXT DEFAULT NULL");
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

// 4. PROCESS TICKET UPDATE (Admin Action Form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $t_id = (int)$_POST['ticket_id'];
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : NULL;
    $status = $_POST['status'];
    $admin_note = trim($_POST['admin_note']);

    $upd_stmt = $conn->prepare("UPDATE tickets SET assigned_to = ?, status = ?, admin_note = ? WHERE id = ?");
    $upd_stmt->bind_param("issi", $assigned_to, $status, $admin_note, $t_id);
    
    if ($upd_stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Ticket updated successfully!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Failed to update ticket.'];
    }
    
    // Redirect to self to prevent form resubmission on refresh
    header("Location: view_ticket_details.php?id=" . $t_id);
    exit();
}

// 5. FETCH TICKET DETAILS
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$t_stmt = $conn->prepare("
    SELECT t.*, 
           COALESCE(ep.full_name, u.username, 'Unknown User') as requester_name, 
           u.email as requester_email,
           COALESCE(a_ep.full_name, a_u.username, 'Unassigned') as assigned_name
    FROM tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
    LEFT JOIN users a_u ON t.assigned_to = a_u.id
    LEFT JOIN employee_profiles a_ep ON a_u.id = a_ep.user_id
    WHERE t.id = ?
");
$t_stmt->bind_param("i", $ticket_id);
$t_stmt->execute();
$ticket = $t_stmt->get_result()->fetch_assoc();

if (!$ticket) {
    echo "<script>alert('Ticket not found!'); window.location.href='manage_tickets.php';</script>";
    exit();
}

// 6. FETCH IT STAFF FOR DROPDOWN
$it_staff = [];
$staff_res = $conn->query("SELECT u.id, COALESCE(ep.full_name, u.username) as name, u.role 
                           FROM users u 
                           LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                           WHERE u.role IN ('System Admin', 'IT Admin', 'IT Executive')");
while ($s = $staff_res->fetch_assoc()) {
    $it_staff[] = $s;
}

// Helpers for badges
function getPriorityBadge($priority) {
    switch (strtolower($priority)) {
        case 'low': return 'bg-info text-white';
        case 'medium': return 'bg-warning text-dark';
        case 'high': 
        case 'critical': return 'bg-danger text-white';
        default: return 'bg-secondary text-white';
    }
}

function getStatusBadge($status) {
    switch (strtolower($status)) {
        case 'open': return 'bg-primary text-white';
        case 'in progress': return 'bg-warning text-dark';
        case 'waiting on user': return 'bg-secondary text-white';
        case 'resolved': 
        case 'closed': return 'bg-success text-white';
        default: return 'bg-dark text-white';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= htmlspecialchars($ticket['ticket_code']) ?> - Details</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-color: #1b5a5a;
            --primary-light: #267a7a;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --border-color: #e2e8f0;
            --sidebar-width: 95px;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* Standardized Main Content Layout */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        .ticket-header {
            background: var(--primary-color);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1rem 1.5rem;
        }

        .ticket-info-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid var(--border-color);
        }

        .admin-action-box {
            background: #f0fdfa; /* Light teal tint */
            border: 1px solid #ccfbf1;
            border-radius: 12px;
            padding: 24px;
        }

        .activity-log .alert {
            margin-bottom: 8px;
            font-size: 0.85rem;
            border-left: 4px solid var(--primary-color);
            border-radius: 4px;
            background: white;
            border-top: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .btn-brand {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }

        .btn-brand:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
            color: white;
        }

        .card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            .admin-action-box {
                position: static !important;
                margin-top: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <div class="main-content" id="mainContent">

        <?php include $headerPath; ?>

        <div class="container-fluid p-4 max-w-[1600px] mx-auto">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h3 class="mb-0" style="color: var(--primary-color); font-weight: 700;">
                    <i class="fas fa-ticket-alt me-2"></i>
                    Ticket #<?= htmlspecialchars($ticket['ticket_code']) ?>
                </h3>
                <a href="manage_tickets.php" class="btn btn-outline-secondary fw-medium">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <div class="row">

                <div class="col-lg-8">

                    <div class="card mb-4">
                        <div class="card-header ticket-header d-flex justify-content-between align-items-center border-0">
                            <span class="fw-bold fs-5">Ticket #<?= htmlspecialchars($ticket['ticket_code']) ?></span>
                            <span class="opacity-75 small">Raised by: <?= htmlspecialchars($ticket['requester_name']) ?></span>
                        </div>
                        <div class="card-body p-4">
                            <h4 class="mb-4 text-dark fw-bold"><?= htmlspecialchars($ticket['subject']) ?></h4>

                            <div class="row mb-4 g-4">
                                <div class="col-md-4">
                                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Department</div>
                                    <div class="fw-medium"><?= htmlspecialchars($ticket['department']) ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Priority</div>
                                    <span class="badge <?= getPriorityBadge($ticket['priority']) ?> px-3 py-2 fs-6">
                                        <?= htmlspecialchars($ticket['priority']) ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Current Status</div>
                                    <span class="badge <?= getStatusBadge($ticket['status']) ?> px-3 py-2 fs-6 shadow-sm">
                                        <?= htmlspecialchars($ticket['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="ticket-info-box mb-4">
                                <label class="text-muted small fw-bold text-uppercase tracking-wider mb-2">Detailed Description</label>
                                <p class="mb-0 text-dark" style="line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars($ticket['description'])) ?>
                                </p>
                            </div>

                            <?php if (!empty($ticket['attachment'])): 
                                // Determine proper path based on string contents
                                $attachPath = $ticket['attachment'];
                                if (!str_starts_with($attachPath, 'uploads/')) {
                                    $attachPath = 'uploads/tickets/' . $attachPath;
                                }
                                $attachPath = (file_exists('../' . $attachPath)) ? '../' . $attachPath : $attachPath;
                            ?>
                            <div>
                                <label class="text-muted small fw-bold text-uppercase tracking-wider mb-2">Attachments</label>
                                <div>
                                    <a href="<?= htmlspecialchars($attachPath) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-paperclip me-1"></i> View Attachment
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light fw-bold text-secondary border-bottom">
                            <i class="fas fa-history me-2"></i>Activity Log
                        </div>
                        <div class="card-body activity-log bg-light">
                            <?php if (!empty($ticket['admin_note'])): ?>
                                <div class="alert text-dark shadow-sm">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted"><i class="fas fa-user-cog me-1"></i> Admin Update</small>
                                        <small class="text-muted"><?= date('d M Y, h:i A', strtotime($ticket['updated_at'])) ?></small>
                                    </div>
                                    <div class="fw-medium">Status changed to: <?= htmlspecialchars($ticket['status']) ?></div>
                                    <div class="mt-2 text-secondary bg-white p-2 rounded border"><strong>Note:</strong> <?= nl2br(htmlspecialchars($ticket['admin_note'])) ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="alert text-dark shadow-sm opacity-75">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted"><i class="fas fa-user me-1"></i> Ticket Created</small>
                                    <small class="text-muted"><?= date('d M Y, h:i A', strtotime($ticket['created_at'])) ?></small>
                                </div>
                                <div class="fw-medium"><?= htmlspecialchars($ticket['requester_name']) ?> submitted the ticket.</div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="col-lg-4">
                    <div class="admin-action-box static-top shadow-sm" style="top: 20px;">
                        <h5 class="text-teal-800 fw-bold mb-4" style="color: var(--primary-color);">
                            <i class="fas fa-tools me-2"></i>Admin Actions
                        </h5>

                        <form action="" method="POST">
                            <input type="hidden" name="update_ticket" value="1">
                            <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticket['id']) ?>">

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Assign Engineer</label>
                                <select class="form-select shadow-none border-secondary-subtle" name="assigned_to">
                                    <option value="">-- Select Executive --</option>
                                    <optgroup label="Internal IT Team">
                                        <?php foreach ($it_staff as $staff): ?>
                                            <option value="<?= $staff['id'] ?>" <?= ($ticket['assigned_to'] == $staff['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($staff['name']) ?> (<?= htmlspecialchars($staff['role']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Update Status</label>
                                <select class="form-select shadow-none border-secondary-subtle" name="status">
                                    <option value="Open" <?= $ticket['status']==='Open' ? 'selected' : '' ?>>Open</option>
                                    <option value="In Progress" <?= $ticket['status']==='In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Waiting on User" <?= $ticket['status']==='Waiting on User' ? 'selected' : '' ?>>Waiting on User</option>
                                    <option value="Resolved" <?= $ticket['status']==='Resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="Closed" <?= $ticket['status']==='Closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Internal Admin Note <span class="fw-normal text-muted text-lowercase">(not visible to user)</span></label>
                                <textarea class="form-control shadow-none border-secondary-subtle" name="admin_note" rows="5" 
                                    placeholder="Add progress notes or resolution details here..."><?= htmlspecialchars($ticket['admin_note'] ?? '') ?></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-brand btn-lg shadow-sm">
                                    <i class="fas fa-save me-2"></i> Update Ticket
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['toast'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: '<?= $_SESSION['toast']['type'] ?>',
                    title: '<?= $_SESSION['toast']['msg'] ?>',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        </script>
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sidebar Layout Integration
        function setupLayoutObserver() {
            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainContent = document.getElementById('mainContent');
            if (!primarySidebar || !mainContent) return;

            const updateMargin = () => {
                if (window.innerWidth <= 992) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                    return;
                }
                let totalWidth = primarySidebar.offsetWidth;
                if (secondarySidebar && secondarySidebar.classList.contains('open')) {
                    totalWidth += secondarySidebar.offsetWidth;
                }
                mainContent.style.marginLeft = totalWidth + 'px';
                mainContent.style.width = `calc(100% - ${totalWidth}px)`;
            };

            new ResizeObserver(() => updateMargin()).observe(primarySidebar);
            if (secondarySidebar) {
                new MutationObserver(() => updateMargin()).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
            }
            window.addEventListener('resize', updateMargin);
            updateMargin();
        }
        document.addEventListener('DOMContentLoaded', setupLayoutObserver);
    </script>

</body>
</html>