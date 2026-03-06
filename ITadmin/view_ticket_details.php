<?php
// view_ticket_details.php - IT Admin Ticket View

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

// 3. SILENT SCHEMA UPDATE (Safety Catch)
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS assigned_to INT DEFAULT NULL");
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

// 4. PROCESS TICKET UPDATE (Admin Action Form - Auto Update Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $t_id = (int)$_POST['ticket_id'];
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : NULL;
    
    // BACKEND SECURITY CHECK: Prevent modifying already resolved/closed tickets
    $check_stmt = $conn->prepare("SELECT status FROM tickets WHERE id = ?");
    $check_stmt->bind_param("i", $t_id);
    $check_stmt->execute();
    $current_status = strtolower($check_stmt->get_result()->fetch_assoc()['status'] ?? '');
    $check_stmt->close();

    if (in_array($current_status, ['resolved', 'closed'])) {
        $_SESSION['toast'] = ['type' => 'warning', 'msg' => 'Action denied: Ticket is already closed or resolved.'];
    } else {
        // Automatically set status to "In Progress" if an executive is assigned, else "Open"
        $status = $assigned_to ? 'In Progress' : 'Open';

        $upd_stmt = $conn->prepare("UPDATE tickets SET assigned_to = ?, status = ? WHERE id = ?");
        $upd_stmt->bind_param("isi", $assigned_to, $status, $t_id);
        
        if ($upd_stmt->execute()) {
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Ticket assigned successfully!'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Failed to assign ticket.'];
        }
        $upd_stmt->close();
    }
    
    header("Location: view_ticket_details.php?id=" . $t_id);
    exit();
}

// 5. FETCH TICKET DETAILS (Including the new Executive Resolution Columns)
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

$is_locked = in_array(strtolower($ticket['status']), ['resolved', 'closed']);

// 6. FETCH IT STAFF FOR DROPDOWN
$it_staff = [];
if (!$is_locked) {
    $staff_res = $conn->query("SELECT u.id, COALESCE(ep.full_name, u.username) as name, u.role 
                               FROM users u 
                               LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                               WHERE u.role IN ('System Admin', 'IT Admin', 'IT Executive')");
    while ($s = $staff_res->fetch_assoc()) {
        $it_staff[] = $s;
    }
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
        case 'waiting for parts': return 'bg-secondary text-white';
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

        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        .ticket-header { background: var(--primary-color); color: white; border-radius: 12px 12px 0 0; padding: 1.2rem 1.5rem; }
        
        .ticket-info-box { background: #f8fafc; border-radius: 8px; padding: 20px; border: 1px solid var(--border-color); }

        .admin-action-box { background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 12px; padding: 24px; }
        .admin-action-box.locked { background: #f8fafc; border: 1px solid #e2e8f0; }

        .activity-log .alert { margin-bottom: 8px; font-size: 0.85rem; border-left: 4px solid var(--primary-color); border-radius: 4px; background: white; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }

        .btn-brand { background-color: var(--primary-color); border-color: var(--primary-color); color: white; font-weight: 500; }
        .btn-brand:hover { background-color: var(--primary-light); border-color: var(--primary-light); color: white; }

        .card { border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }

        @media (max-width: 992px) {
            .main-content { margin-left: 0 !important; width: 100% !important; }
            .admin-action-box { position: static !important; margin-top: 1.5rem; }
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
                    <i class="fas fa-ticket-alt me-2"></i> Ticket #<?= htmlspecialchars($ticket['ticket_code'] ?? $ticket['id']) ?>
                </h3>
                <a href="manage_tickets.php" class="btn btn-outline-secondary fw-medium">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <div class="row">

                <div class="col-lg-8">

                    <div class="card mb-4">
                        <div class="card-header ticket-header d-flex justify-content-between align-items-center border-0">
                            <span class="fw-bold fs-5">Ticket Details</span>
                            <span class="opacity-75 small">Reported via: <?= htmlspecialchars($ticket['source'] ?? 'System') ?></span>
                        </div>
                        <div class="card-body p-4">
                            <h4 class="mb-4 text-dark fw-bold"><?= htmlspecialchars($ticket['subject']) ?></h4>

                            <div class="row mb-4 g-4">
                                <div class="col-md-3">
                                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Requester</div>
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($ticket['requester_name']) ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Department</div>
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($ticket['department']) ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Priority</div>
                                    <span class="badge <?= getPriorityBadge($ticket['priority']) ?> px-3 py-2 fs-6">
                                        <?= htmlspecialchars($ticket['priority']) ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Status</div>
                                    <span class="badge <?= getStatusBadge($ticket['status']) ?> px-3 py-2 fs-6 shadow-sm">
                                        <?= htmlspecialchars($ticket['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="ticket-info-box mb-4">
                                <label class="text-muted small fw-bold text-uppercase tracking-wider mb-2">Description of Issue</label>
                                <p class="mb-0 text-dark" style="line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars($ticket['description'])) ?>
                                </p>
                            </div>

                            <?php if (!empty($ticket['attachment'])): 
                                $attachPath = $ticket['attachment'];
                                if (!str_starts_with($attachPath, 'uploads/')) { $attachPath = 'uploads/tickets/' . $attachPath; }
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

                    <?php if (!empty($ticket['solution']) || !empty($ticket['diagnosis'])): ?>
                    <div class="card mb-4 border-success shadow-sm" style="border-width: 2px;">
                        <div class="card-header bg-success bg-opacity-10 border-bottom border-success text-success fw-bold d-flex justify-content-between align-items-center py-3">
                            <span class="fs-5"><i class="fas fa-clipboard-check me-2"></i>Executive Resolution Report</span>
                            <?php if(!empty($ticket['time_taken'])): ?>
                                <span class="badge bg-success fs-6 shadow-sm"><i class="fas fa-stopwatch me-1"></i> Time Taken: <?= htmlspecialchars($ticket['time_taken']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php if (!empty($ticket['diagnosis'])): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold text-secondary text-uppercase mb-2" style="font-size:0.8rem; letter-spacing: 1px;">Diagnosis / Root Cause</h6>
                                <p class="text-dark bg-light p-3 rounded border border-light-subtle m-0" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($ticket['diagnosis'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($ticket['solution'])): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold text-secondary text-uppercase mb-2" style="font-size:0.8rem; letter-spacing: 1px;">Solution / Steps Taken</h6>
                                <p class="text-dark bg-light p-3 rounded border border-light-subtle m-0" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($ticket['solution'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($ticket['new_part_name']) || !empty($ticket['old_part_name'])): ?>
                            <h6 class="fw-bold text-secondary text-uppercase mt-4 mb-3" style="font-size:0.8rem; letter-spacing: 1px;">
                                <i class="fas fa-microchip me-1"></i> Hardware Replacement Log
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded h-100">
                                        <span class="d-block text-danger small fw-bold mb-2"><i class="fas fa-arrow-down me-1"></i> OLD / FAULTY PART</span>
                                        <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($ticket['old_part_name'] ?: 'Not Specified') ?></div>
                                        <div class="text-muted small mt-1 fw-medium">Serial No: <?= htmlspecialchars($ticket['old_part_serial'] ?: 'N/A') ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded h-100">
                                        <span class="d-block text-success small fw-bold mb-2"><i class="fas fa-arrow-up me-1"></i> NEW / INSTALLED PART</span>
                                        <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($ticket['new_part_name'] ?: 'Not Specified') ?></div>
                                        <div class="text-muted small mt-1 fw-medium">Serial No: <?= htmlspecialchars($ticket['new_part_serial'] ?: 'N/A') ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light fw-bold text-secondary border-bottom py-3">
                            <i class="fas fa-history me-2"></i>Timeline Activity Log
                        </div>
                        <div class="card-body activity-log bg-light p-4">
                            
                            <?php if (!empty($ticket['resolved_at'])): ?>
                                <div class="alert text-dark shadow-sm" style="border-left: 4px solid #198754;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Ticket Resolved</small>
                                        <small class="text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($ticket['resolved_at'])) ?></small>
                                    </div>
                                    <div class="fw-medium text-dark">Resolution submitted by <?= htmlspecialchars($ticket['assigned_name']) ?>.</div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($ticket['assigned_to'])): ?>
                                <div class="alert text-dark shadow-sm">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted fw-bold"><i class="fas fa-user-cog me-1"></i> Ticket Assigned</small>
                                        <small class="text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($ticket['updated_at'])) ?></small>
                                    </div>
                                    <div class="fw-medium text-dark">Assigned to IT Executive: <?= htmlspecialchars($ticket['assigned_name']) ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="alert text-dark shadow-sm opacity-75">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted fw-bold"><i class="fas fa-ticket-alt me-1"></i> Ticket Created</small>
                                    <small class="text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($ticket['created_at'])) ?></small>
                                </div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($ticket['requester_name']) ?> submitted the initial ticket.</div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="col-lg-4">
                    <div class="admin-action-box static-top shadow-sm <?= $is_locked ? 'locked' : '' ?>" style="top: 20px;">
                        
                        <?php if ($is_locked): ?>
                            <h5 class="text-secondary fw-bold mb-3">
                                <i class="fas fa-lock me-2 text-success"></i>Ticket Locked
                            </h5>
                            
                            <div class="bg-white border rounded p-3 mb-3 shadow-sm">
                                <p class="text-muted small mb-0">This ticket has been marked as <strong><?= htmlspecialchars($ticket['status']) ?></strong>. No further administrative actions or assignments can be made.</p>
                            </div>

                            <?php if (!empty($ticket['assigned_to'])): ?>
                                <div class="bg-white border rounded p-3 shadow-sm">
                                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Handled By</span>
                                    <div class="d-flex align-items-center fw-medium text-dark mt-2">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-primary me-2" style="width:32px;height:32px;font-size:14px;font-weight:bold;">
                                            <?= substr(htmlspecialchars($ticket['assigned_name']), 0, 1); ?>
                                        </div>
                                        <?= htmlspecialchars($ticket['assigned_name']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <h5 class="text-teal-800 fw-bold mb-4" style="color: var(--primary-color);">
                                <i class="fas fa-tools me-2"></i>Admin Actions
                            </h5>

                            <form action="" method="POST">
                                <input type="hidden" name="update_ticket" value="1">
                                <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticket['id']) ?>">

                                <div class="mb-4">
                                    <label class="form-label fw-bold text-secondary small text-uppercase">Assign IT Executive</label>
                                    <select class="form-select shadow-none border-secondary-subtle py-2" name="assigned_to" required>
                                        <option value="">-- Select Executive --</option>
                                        <optgroup label="Internal IT Team">
                                            <?php foreach ($it_staff as $staff): ?>
                                                <option value="<?= $staff['id'] ?>" <?= ($ticket['assigned_to'] == $staff['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($staff['name']) ?> (<?= htmlspecialchars($staff['role']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                    <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle"></i> Assigning will automatically mark the ticket as "In Progress" and start the resolution timer for the executive.</small>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-brand btn-lg shadow-sm">
                                        <i class="fas fa-user-check me-2"></i> Assign Ticket
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

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