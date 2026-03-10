<?php
// it_exec_ticket_action.php - IT Executive Dashboard

// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'IT Executive';

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

// =========================================================================
// 3. PROCESS TICKET RESOLUTION & AUTO-CALCULATE TIMINGS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    
    // A. BULLETPROOF DATABASE PATCHER (Checks each column individually to prevent duplicate crashes)
    $columns_to_check = [
        'diagnosis' => 'TEXT DEFAULT NULL',
        'solution' => 'TEXT DEFAULT NULL',
        'time_taken' => 'VARCHAR(100) DEFAULT NULL',
        'old_part_name' => 'VARCHAR(255) DEFAULT NULL',
        'old_part_serial' => 'VARCHAR(100) DEFAULT NULL',
        'new_part_name' => 'VARCHAR(255) DEFAULT NULL',
        'new_part_serial' => 'VARCHAR(100) DEFAULT NULL',
        'resolved_at' => 'DATETIME DEFAULT NULL'
    ];

    foreach ($columns_to_check as $col_name => $col_type) {
        $check = $conn->query("SHOW COLUMNS FROM tickets LIKE '$col_name'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE tickets ADD COLUMN $col_name $col_type");
        }
    }
    
    // Fix Status Enum if needed
    $conn->query("ALTER TABLE tickets MODIFY COLUMN status ENUM('Open', 'In Progress', 'Waiting for Parts', 'Resolved', 'Closed') DEFAULT 'Open'");

    // B. Sanitize Inputs
    $ticket_id       = intval($_POST['ticket_id'] ?? 0);
    $raw_status      = $_POST['status'] ?? 'Open';
    $diagnosis       = trim($_POST['diagnosis'] ?? '');
    $solution        = trim($_POST['solution'] ?? '');
    $old_part_name   = trim($_POST['old_part_name'] ?? '');
    $old_part_serial = trim($_POST['old_part_serial'] ?? '');
    $new_part_name   = trim($_POST['new_part_name'] ?? '');
    $new_part_serial = trim($_POST['new_part_serial'] ?? '');

    if ($ticket_id > 0) {
        // C. Map Status Correctly
        $status_map = [
            'in_progress'   => 'In Progress',
            'waiting_parts' => 'Waiting for Parts',
            'completed'     => 'Resolved',
            'rejected'      => 'Closed',
            'Open'          => 'Open',
            'In Progress'   => 'In Progress',
            'Waiting for Parts' => 'Waiting for Parts',
            'Resolved'      => 'Resolved',
            'Closed'        => 'Closed'
        ];
        $status = $status_map[$raw_status] ?? 'Open';

        // D. AUTO-CALCULATE TIME TAKEN
        // Fetch created_at timestamp to calculate the exact duration
        $t_query = $conn->prepare("SELECT created_at, time_taken FROM tickets WHERE id = ?");
        $t_query->bind_param("i", $ticket_id);
        $t_query->execute();
        $t_data = $t_query->get_result()->fetch_assoc();
        $t_query->close();

        $time_taken = $t_data['time_taken']; 
        $resolved_at = null;

        // Only calculate time IF the status is changing to Resolved/Closed AND time_taken is empty
        if (in_array($status, ['Resolved', 'Closed']) && empty($time_taken)) {
            $start_time = strtotime($t_data['created_at']);
            $end_time = time(); // Current exact timestamp
            $diff = max(0, $end_time - $start_time);
            
            $days = floor($diff / 86400);
            $hours = floor(($diff % 86400) / 3600);
            $mins = floor(($diff % 3600) / 60);

            $time_parts = [];
            if ($days > 0) $time_parts[] = "{$days} Days";
            if ($hours > 0) $time_parts[] = "{$hours} Hrs";
            if ($mins > 0) $time_parts[] = "{$mins} Mins";
            if (empty($time_parts)) $time_parts[] = "Just now";

            $time_taken = implode(" ", $time_parts);
            $resolved_at = date('Y-m-d H:i:s');
        }

        // E. Update Query
        $sql = "UPDATE tickets 
                SET status = ?, diagnosis = ?, solution = ?, 
                    old_part_name = ?, old_part_serial = ?, new_part_name = ?, new_part_serial = ? ";
        
        // Append resolution timings if computed
        if ($resolved_at) {
            $sql .= ", time_taken = '$time_taken', resolved_at = '$resolved_at' ";
        }
        
        $sql .= "WHERE id = ? AND assigned_to = ?";
                
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssssssii", $status, $diagnosis, $solution, $old_part_name, $old_part_serial, $new_part_name, $new_part_serial, $ticket_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Ticket Resolution Saved Successfully!'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Error updating database.'];
            }
            $stmt->close();
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// =========================================================================
// 4. FETCH ASSIGNED TICKETS & CALCULATE STATS
// =========================================================================
$tickets = [];
$stats = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0
];

// FIXED DUPLICATION: Using GROUP BY u.id and MAX() ensures no duplicate users from profile table
$query = "SELECT t.*, 
                 COALESCE(MAX(ep.full_name), u.name, u.username, 'Unknown User') as requester_name,
                 u.email as requester_email
          FROM tickets t 
          LEFT JOIN users u ON t.user_id = u.id 
          LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
          WHERE t.assigned_to = ? 
          GROUP BY t.id
          ORDER BY FIELD(t.status, 'Open', 'In Progress', 'Waiting for Parts', 'Resolved', 'Closed'), t.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
    $stats['total']++;
    
    $status_lower = strtolower($row['status']);
    if ($status_lower === 'open') {
        $stats['open']++;
    } elseif (in_array($status_lower, ['in progress', 'waiting for parts'])) {
        $stats['in_progress']++;
    } elseif (in_array($status_lower, ['resolved', 'closed'])) {
        $stats['resolved']++;
    }
}
$stmt->close();

// Helpers for badges
function getPriorityBadge($priority) {
    switch (strtolower($priority)) {
        case 'low': return 'bg-info text-white';
        case 'medium': return 'bg-warning text-dark';
        case 'high': return 'bg-orange text-white'; 
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
    <title>My Assigned Tickets - IT Executive</title>
    
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

        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        /* Table Card */
        .table-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            overflow: hidden;
        }
        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .table td {
            padding: 1rem;
            font-size: 0.9rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .table tbody tr:hover { background-color: #f8fafc; }

        .bg-orange { background-color: #f97316; }
        .badge { padding: 0.4em 0.7em; font-weight: 600; border-radius: 6px; font-size: 0.75rem;}

        .btn-brand { background-color: var(--primary-color); color: white; border: none; font-weight: 500; }
        .btn-brand:hover { background-color: var(--primary-light); color: white; }

        .modal-header-brand { background-color: var(--primary-color); color: white; }
        .info-label { font-size: 0.8rem; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 0.2rem;}
        .info-data { font-size: 0.95rem; color: #1e293b; font-weight: 500; margin-bottom: 1rem;}

        /* Custom Input Fixes */
        input:disabled, select:disabled, textarea:disabled {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
            opacity: 1;
            border-color: #e2e8f0;
        }
        
        .auto-calc-box {
            background-color: #eefcfd;
            border: 1px dashed #1b5a5a;
            color: #1b5a5a;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
        }

        @media (max-width: 992px) {
            .main-content { margin-left: 0 !important; width: 100% !important; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <div class="main-content" id="mainContent">

        <?php include $headerPath; ?>

        <div class="container-fluid p-4 max-w-[1600px] mx-auto">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0" style="color: var(--primary-color); font-weight: 700;">
                    <i class="fas fa-headset me-2"></i> My Assigned Tickets
                </h3>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card border-start border-4 border-primary">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-ticket-alt"></i></div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Total Assigned</div>
                            <div class="fs-4 fw-bold text-dark"><?= $stats['total'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card border-start border-4 border-danger">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-exclamation-circle"></i></div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Open / Pending</div>
                            <div class="fs-4 fw-bold text-dark"><?= $stats['open'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card border-start border-4 border-warning">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-spinner"></i></div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">In Progress</div>
                            <div class="fs-4 fw-bold text-dark"><?= $stats['in_progress'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card border-start border-4 border-success">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Resolved</div>
                            <div class="fs-4 fw-bold text-dark"><?= $stats['resolved'] ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h5 class="mb-0 fw-bold text-secondary fs-6"><i class="fas fa-list-ul me-2"></i> Work Queue</h5>
                    <div class="position-relative" style="width: 250px;">
                        <i class="fa fa-search position-absolute text-muted" style="top: 10px; left: 12px;"></i>
                        <input type="text" id="searchTicket" class="form-control form-control-sm ps-5 shadow-none" placeholder="Search Subject or ID..." onkeyup="filterTickets()">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" id="ticketsTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Ticket ID</th>
                                <th>Date Received</th>
                                <th>Requester</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-mug-hot fa-2x mb-3 text-secondary opacity-50"></i>
                                        <p class="mb-0">You have no assigned tickets right now. Great job!</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): 
                                    // Parse attachment safely
                                    $attachPath = $ticket['attachment'];
                                    if (!empty($attachPath)) {
                                        if (!str_starts_with($attachPath, 'uploads/')) { $attachPath = 'uploads/tickets/' . $attachPath; }
                                        $attachPath = (file_exists('../' . $attachPath)) ? '../' . $attachPath : $attachPath;
                                    } else {
                                        $attachPath = '';
                                    }
                                    
                                    $is_resolved = in_array(strtolower($ticket['status']), ['resolved', 'closed']);

                                    // Build Secure Data Payload
                                    $t_data = htmlspecialchars(json_encode([
                                        'id' => $ticket['id'],
                                        'code' => $ticket['ticket_code'] ?? $ticket['id'],
                                        'subject' => $ticket['subject'],
                                        'requester' => $ticket['requester_name'],
                                        'priority' => $ticket['priority'],
                                        'status' => $ticket['status'],
                                        'date' => date('d M Y, h:i A', strtotime($ticket['created_at'])),
                                        'desc' => $ticket['description'],
                                        'attachment' => $attachPath,
                                        'is_resolved' => $is_resolved,
                                        // Specific Fields
                                        'diagnosis' => $ticket['diagnosis'] ?? '',
                                        'solution' => $ticket['solution'] ?? '',
                                        'time_taken' => $ticket['time_taken'] ?? '',
                                        'old_part_name' => $ticket['old_part_name'] ?? '',
                                        'old_part_serial' => $ticket['old_part_serial'] ?? '',
                                        'new_part_name' => $ticket['new_part_name'] ?? '',
                                        'new_part_serial' => $ticket['new_part_serial'] ?? ''
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="ticket-row">
                                        <td class="ps-4 fw-bold text-secondary font-monospace ticket-id">
                                            #<?= htmlspecialchars($ticket['ticket_code'] ?? $ticket['id']) ?>
                                        </td>
                                        <td><?= date('d M Y, h:i A', strtotime($ticket['created_at'])) ?></td>
                                        <td class="fw-medium text-dark"><?= htmlspecialchars($ticket['requester_name']) ?></td>
                                        <td class="ticket-subject fw-medium text-dark" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($ticket['subject']) ?>">
                                            <?= htmlspecialchars($ticket['subject']) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= getPriorityBadge($ticket['priority']) ?>">
                                                <?= htmlspecialchars($ticket['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= getStatusBadge($ticket['status']) ?>">
                                                <?= htmlspecialchars($ticket['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($is_resolved): ?>
                                                <button class="btn btn-sm btn-outline-success shadow-sm" onclick="openActionModal(this)" data-ticket="<?= $t_data ?>">
                                                    <i class="fas fa-check-double me-1"></i> View Resolved
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-brand shadow-sm" onclick="openActionModal(this)" data-ticket="<?= $t_data ?>">
                                                    <i class="fas fa-bolt me-1"></i> Take Action
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bg-light text-muted text-center py-3 border-top" style="font-size: 0.85rem;">
                    Showing <span id="visibleCount" class="fw-bold"><?php echo count($tickets); ?></span> assigned tickets
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header modal-header-brand border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-ticket-alt me-2"></i> Ticket <span id="m_code" class="ms-1"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        
                        <div class="col-md-5 p-4 bg-white border-end">
                            <h5 id="m_subject" class="fw-bold mb-4 text-dark">Subject</h5>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="info-label">Requester</div>
                                    <div class="info-data" id="m_requester">User Name</div>
                                </div>
                                <div class="col-6">
                                    <div class="info-label">Date Submitted</div>
                                    <div class="info-data" id="m_date">Date</div>
                                </div>
                            </div>

                            <div class="info-label mt-2">Description of Issue</div>
                            <div class="p-3 bg-light rounded border border-light-subtle text-dark mb-4" style="font-size: 0.9rem; line-height: 1.6; max-height: 200px; overflow-y: auto; white-space: pre-wrap;" id="m_desc">Description goes here...</div>

                            <div id="m_attach_container" style="display: none;">
                                <div class="info-label">Attached File</div>
                                <a href="#" id="m_attach_link" target="_blank" class="btn btn-sm btn-outline-secondary mt-1">
                                    <i class="fas fa-paperclip me-1"></i> View Attachment
                                </a>
                            </div>
                        </div>

                        <div class="col-md-7 p-4 bg-light">
                            <h6 id="formTitle" class="fw-bold text-primary mb-3" style="color: var(--primary-color) !important;">
                                <i class="fas fa-clipboard-check me-2"></i>Resolution Log
                            </h6>
                            
                            <form action="" method="POST" id="resolutionForm">
                                <input type="hidden" name="update_ticket" value="1">
                                <input type="hidden" name="ticket_id" id="m_id" value="">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-secondary">Update Status <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" name="status" id="m_status" required>
                                            <option value="Open">Open</option>
                                            <option value="In Progress">In Progress</option>
                                            <option value="Waiting for Parts">Waiting for Parts</option>
                                            <option value="Resolved">Resolved</option>
                                            <option value="Closed">Closed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-secondary">Total Time Taken</label>
                                        <div id="m_time_display" class="auto-calc-box w-100">Will be calculated automatically upon resolution.</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-secondary">Diagnosis / Root Cause</label>
                                    <textarea class="form-control shadow-none border-secondary-subtle" name="diagnosis" id="m_diagnosis" rows="2" placeholder="What exactly caused the issue?"></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-secondary">Solution / Steps Taken <span class="text-danger">*</span></label>
                                    <textarea class="form-control shadow-none border-secondary-subtle" name="solution" id="m_solution" rows="2" placeholder="What specific steps were taken to resolve it?" required></textarea>
                                </div>

                                <div class="border border-secondary-subtle rounded p-3 bg-white mb-4 shadow-sm">
                                    <label class="form-label fw-bold text-secondary small text-uppercase mb-3 d-block border-bottom pb-2">
                                        <i class="fas fa-microchip me-2 text-primary"></i> Hardware Replacement Tracker (If applicable)
                                    </label>
                                    <div class="row g-4">
                                        <div class="col-md-6 border-end border-light-subtle">
                                            <span class="d-block text-danger small fw-bold mb-2"><i class="fas fa-arrow-down me-1"></i> OLD / FAULTY PART</span>
                                            <input type="text" class="form-control shadow-none form-control-sm mb-2 border-secondary-subtle" name="old_part_name" id="m_old_part_name" placeholder="Part Name (e.g., HDD, RAM)">
                                            <input type="text" class="form-control shadow-none form-control-sm border-secondary-subtle" name="old_part_serial" id="m_old_part_serial" placeholder="Old Serial Number">
                                        </div>
                                        <div class="col-md-6">
                                            <span class="d-block text-success small fw-bold mb-2"><i class="fas fa-arrow-up me-1"></i> NEW / INSTALLED PART</span>
                                            <input type="text" class="form-control shadow-none form-control-sm mb-2 border-secondary-subtle" name="new_part_name" id="m_new_part_name" placeholder="Part Name (e.g., 500GB SSD)">
                                            <input type="text" class="form-control shadow-none form-control-sm border-secondary-subtle" name="new_part_serial" id="m_new_part_serial" placeholder="New Serial Number">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid mt-auto">
                                    <button type="submit" class="btn btn-brand py-2 shadow-sm" id="submitResolutionBtn">
                                        <i class="fas fa-check-double me-1"></i> Save & Execute Resolution
                                    </button>
                                </div>
                            </form>
                        </div>
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

        // Search Filter
        function filterTickets() {
            const searchInput = document.getElementById('searchTicket').value.toLowerCase();
            const rows = document.querySelectorAll('.ticket-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const ticketId = row.querySelector('.ticket-id').innerText.toLowerCase();
                const subject = row.querySelector('.ticket-subject').innerText.toLowerCase();

                if (ticketId.includes(searchInput) || subject.includes(searchInput)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            document.getElementById('visibleCount').innerText = visibleCount;
        }

        // Modal Data Injection & Prevention of Double Submit
        const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
        
        function openActionModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-ticket'));
            const isResolved = data.is_resolved;
            
            // Set Left Panel Data
            document.getElementById('m_id').value = data.id;
            document.getElementById('m_code').innerText = '#' + data.code;
            document.getElementById('m_subject').innerText = data.subject;
            document.getElementById('m_requester').innerText = data.requester;
            document.getElementById('m_date').innerText = data.date;
            document.getElementById('m_desc').innerText = data.desc;
            
            // Pre-fill Right Panel Form Data
            document.getElementById('m_status').value = data.status;
            document.getElementById('m_diagnosis').value = data.diagnosis;
            document.getElementById('m_solution').value = data.solution;
            
            // Pre-fill Advanced Hardware Tracking
            document.getElementById('m_old_part_name').value = data.old_part_name;
            document.getElementById('m_old_part_serial').value = data.old_part_serial;
            document.getElementById('m_new_part_name').value = data.new_part_name;
            document.getElementById('m_new_part_serial').value = data.new_part_serial;
            
            // Auto Time Calculated Display
            const timeDisplay = document.getElementById('m_time_display');
            if (data.time_taken && data.time_taken.trim() !== '') {
                timeDisplay.innerHTML = `<i class="fas fa-clock me-1"></i> ${data.time_taken}`;
                timeDisplay.style.backgroundColor = '#f0fdf4';
                timeDisplay.style.borderColor = '#16a34a';
                timeDisplay.style.color = '#16a34a';
            } else {
                timeDisplay.innerHTML = `<i class="fas fa-calculator me-1"></i> Auto-calculates upon resolution`;
                timeDisplay.style.backgroundColor = '#eefcfd';
                timeDisplay.style.borderColor = '#1b5a5a';
                timeDisplay.style.color = '#1b5a5a';
            }

            // Attachment Handling
            const attachContainer = document.getElementById('m_attach_container');
            const attachLink = document.getElementById('m_attach_link');
            if(data.attachment !== '') {
                attachLink.href = data.attachment;
                attachContainer.style.display = 'block';
            } else {
                attachContainer.style.display = 'none';
            }

            // Lock Form if Already Resolved
            const formFields = document.querySelectorAll('#resolutionForm input:not([type="hidden"]), #resolutionForm select, #resolutionForm textarea');
            const submitBtn = document.getElementById('submitResolutionBtn');
            const formTitle = document.getElementById('formTitle');

            if (isResolved) {
                formFields.forEach(f => f.disabled = true);
                submitBtn.style.display = 'none';
                formTitle.innerHTML = '<i class="fas fa-check-circle me-2 text-success"></i>Resolution Details (Locked)';
            } else {
                formFields.forEach(f => f.disabled = false);
                submitBtn.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-double me-1"></i> Save & Execute Resolution';
                formTitle.innerHTML = '<i class="fas fa-clipboard-check me-2"></i>Resolution Log';
            }

            actionModal.show();
        }

        // Prevent Double Submission
        document.getElementById('resolutionForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitResolutionBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Finalizing Resolution...';
        });
    </script>

</body>
</html>