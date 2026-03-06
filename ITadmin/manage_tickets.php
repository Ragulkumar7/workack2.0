<?php
// manage_tickets.php

// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 2. DATABASE CONNECTION & CONFIG (Smart Path Resolver)
date_default_timezone_set('Asia/Kolkata');
$dbPath = 'include/db_connect.php';
$root_path = './';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
    $root_path = '../';
} else {
    die("Critical Error: Cannot find database connection file.");
}

// --- DATABASE PATCHER: Ensure 'source' column exists ---
$check_source = $conn->query("SHOW COLUMNS FROM tickets LIKE 'source'");
if ($check_source && $check_source->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN source VARCHAR(50) DEFAULT 'Portal' AFTER status");
}

// --- POST HANDLER: MANUAL TICKET CREATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    
    // Wipe HTML buffer to prevent redirect crashes
    if(ob_get_length()) ob_clean(); 
    
    $emp_id = (int)$_POST['employee_id'];
    $department = trim($_POST['department']);
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $source = $_POST['source'] ?? 'Walk-in';
    $status = 'Open';

    // File Upload Handling
    $attachment_db_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $root_path . 'uploads/tickets/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        
        $file_name = $_FILES['attachment']['name'];
        $file_size = $_FILES['attachment']['size'];
        $file_tmp = $_FILES['attachment']['tmp_name'];
        
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'docx'];
        
        if (in_array($ext, $allowed) && $file_size <= 5 * 1024 * 1024) {
            $new_file_name = uniqid('tkt_') . '.' . $ext;
            $dest = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $dest)) {
                $attachment_db_path = 'uploads/tickets/' . $new_file_name;
            }
        } else {
            $_SESSION['toast'] = "Invalid file type or size exceeds 5MB.";
            $_SESSION['toast_type'] = "error";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Dynamic Ticket Code Generation (IT1001+)
    $max_id_query = $conn->query("SELECT MAX(id) as max_id FROM tickets");
    $max_id_row = $max_id_query->fetch_assoc();
    $next_id = ($max_id_row['max_id'] ?? 0) + 1;
    $ticket_code = "IT" . (1000 + $next_id);

    // Insert Ticket securely via Prepared Statement
    $stmt = $conn->prepare("INSERT INTO tickets (ticket_code, user_id, department, subject, description, priority, status, source, attachment, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sisssssss", $ticket_code, $emp_id, $department, $subject, $description, $priority, $status, $source, $attachment_db_path);
    
    if ($stmt->execute()) {
        $_SESSION['toast'] = "Ticket created successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast'] = "Error creating ticket: " . $conn->error;
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 3. FETCH DATA FOR UI

// A. Fetch All Employees for the Modal Dropdown (FIXED DUPLICATION)
$users_list = [];
$u_query = "SELECT u.id, 
                   COALESCE(MAX(ep.full_name), u.name, u.username) AS name, 
                   MAX(ep.department) as department, 
                   MAX(ep.emp_id_code) as emp_id_code 
            FROM users u 
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
            GROUP BY u.id
            ORDER BY name ASC";
$u_result = $conn->query($u_query);
while($u = $u_result->fetch_assoc()) {
    $users_list[] = $u;
}

// B. Fetch All Distinct Departments
$departments = [];
$d_query = "SELECT DISTINCT department FROM employee_profiles WHERE department IS NOT NULL AND department != ''";
$d_result = $conn->query($d_query);
if ($d_result) {
    while($d = $d_result->fetch_assoc()) {
        $departments[] = trim($d['department']);
    }
}

// C. Fetch Tickets (FIXED DUPLICATION BY REMOVING EXTRANEOUS JOIN)
$tickets = [];
$query = "SELECT t.*, COALESCE(u.name, u.username, 'Unknown User') as requester_name 
          FROM tickets t 
          LEFT JOIN users u ON t.user_id = u.id 
          ORDER BY t.created_at DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
        // Merge departments used in tickets that might not be in profiles
        if (!empty($row['department']) && !in_array(trim($row['department']), $departments)) {
            $departments[] = trim($row['department']);
        }
    }
}

// Sort the departments alphabetically
$departments = array_unique($departments);
sort($departments);

// Helper functions for badges
function getPriorityBadge($priority) {
    switch (strtolower($priority)) {
        case 'low': return 'bg-info text-white';
        case 'medium': return 'bg-warning text-dark';
        case 'high': return 'bg-orange text-white'; 
        case 'urgent': 
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

// Toast variables from Session
$toast_msg = '';
$toast_type = 'success';
if (isset($_SESSION['toast'])) {
    $toast_msg = $_SESSION['toast'];
    $toast_type = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast']);
    unset($_SESSION['toast_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tickets - IT Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

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

        /* Page-specific styles */
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
        }

        .filter-section {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .table-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            overflow: hidden;
        }

        .table { margin-bottom: 0; }
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

        .btn-brand {
            background-color: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: 0.2s;
            box-shadow: 0 4px 6px rgba(27, 90, 90, 0.2);
        }
        .btn-brand:hover { background-color: var(--primary-light); color: white; transform: translateY(-1px);}

        .btn-outline-brand {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            transition: 0.2s;
        }
        .btn-outline-brand:hover { background-color: #f0fdfa; color: var(--primary-light); border-color: var(--primary-light);}

        .bg-orange { background-color: #f97316; }
        .badge { padding: 0.4em 0.7em; font-weight: 600; border-radius: 6px; font-size: 0.75rem;}

        /* Modal Customizations */
        .modal-header { background-color: #f8fafc; border-bottom: 1px solid var(--border-color); }
        .modal-title { font-weight: 700; color: var(--primary-color); }
        .form-label { font-weight: 700; color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control, .form-select { border-radius: 8px; padding: 0.6rem 1rem; border-color: var(--border-color); font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 0.25rem rgba(27, 90, 90, 0.15); border-color: var(--primary-color); }

        /* Tom Select Overrides */
        .ts-wrapper { flex-grow: 1; }
        .ts-control { border-radius: 0 8px 8px 0 !important; border: 1px solid var(--border-color); padding: 0.6rem 1rem; min-height: 42px; display: flex; align-items: center; font-size: 0.9rem;}
        .input-group > .ts-wrapper { width: 1% !important; }
        .ts-dropdown { z-index: 1060 !important; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--border-color); font-size: 0.9rem; }
        .ts-control.focus { border-color: var(--primary-color); box-shadow: none; }

        /* Toast UI */
        #toast {
            visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center;
            border-radius: 8px; padding: 14px; position: fixed; z-index: 10000; left: 50%; bottom: 30px;
            transform: translateX(-50%); opacity: 0; transition: opacity 0.5s, bottom 0.5s; font-weight: 500; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
        #toast.success { background-color: var(--primary-color); }
        #toast.error { background-color: #ef4444; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0 !important; width: 100% !important; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <div class="main-content" id="mainContent">

        <?php include $headerPath; ?>

        <div class="container-fluid p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h3 class="page-title mb-0">
                    <i class="fas fa-ticket-alt me-2"></i> IT Ticket Management
                </h3>
                <button class="btn btn-brand d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="fa-solid fa-plus"></i> Create Ticket
                </button>
            </div>

            <div class="filter-section d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="position-relative" style="min-width: 250px; flex-grow: 1; max-width: 400px;">
                    <i class="fa fa-search position-absolute text-muted" style="top: 10px; left: 12px;"></i>
                    <input type="text" id="searchTicket" class="form-control ps-5 shadow-none border-secondary-subtle" placeholder="Search by Ticket ID or Subject..." onkeyup="filterTickets()">
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <select id="filterCategory" class="form-select shadow-none border-secondary-subtle w-auto" onchange="filterTickets()">
                        <option value="All">All Departments</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="filterStatus" class="form-select shadow-none border-secondary-subtle w-auto" onchange="filterTickets()">
                        <option value="All">All Statuses</option>
                        <option value="Open">Open</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Waiting on User">Waiting on User</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table mb-0" id="ticketsTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Ticket ID</th>
                                <th>Date Submitted</th>
                                <th>Requested By</th>
                                <th>Subject</th>
                                <th>Department</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Source</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <div class="d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-inbox fa-3x mb-3 text-secondary opacity-25"></i>
                                            <p class="mb-0 fw-medium">No tickets found in the system.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr class="ticket-row" 
                                        data-category="<?php echo htmlspecialchars($ticket['department']); ?>" 
                                        data-status="<?php echo htmlspecialchars($ticket['status']); ?>">
                                        
                                        <td class="ps-4 fw-bold text-secondary font-monospace ticket-id">
                                            #<?php echo htmlspecialchars($ticket['ticket_code']); ?>
                                        </td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($ticket['created_at'])); ?></td>
                                        <td class="fw-medium text-dark">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-primary" style="width:28px;height:28px;font-size:12px;font-weight:bold;">
                                                    <?php echo substr(htmlspecialchars($ticket['requester_name']), 0, 1); ?>
                                                </div>
                                                <?php echo htmlspecialchars($ticket['requester_name']); ?>
                                            </div>
                                        </td>
                                        <td class="ticket-subject fw-medium text-dark" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($ticket['subject']); ?>">
                                            <?php echo htmlspecialchars($ticket['subject']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($ticket['department']); ?></td>
                                        <td>
                                            <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?>">
                                                <?php echo htmlspecialchars($ticket['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadge($ticket['status']); ?>">
                                                <?php echo htmlspecialchars($ticket['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small fw-medium">
                                            <i class="fa-solid fa-headset me-1 opacity-75"></i> <?php echo htmlspecialchars($ticket['source'] ?? 'Portal'); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="view_ticket_details.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-brand me-1" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="bg-light text-muted text-center py-3 border-top" style="font-size: 0.85rem;">
                    Showing <span id="visibleCount" class="fw-bold"><?php echo count($tickets); ?></span> tickets
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="createTicketModal" tabindex="-1" aria-labelledby="createTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTicketModalLabel"><i class="fa-solid fa-plus-circle me-2"></i>Create Manual Ticket</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_ticket">
                    
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Employee <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-user"></i></span>
                                    <select name="employee_id" id="employee_select" required>
                                        <option value="">Search employee name...</option>
                                        <?php foreach ($users_list as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" data-dept="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($user['name']); ?> 
                                                <?php echo !empty($user['emp_id_code']) ? '['.htmlspecialchars($user['emp_id_code']).']' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-building"></i></span>
                                    <select name="department" id="department_select" required>
                                        <option value="">Select or type department...</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Priority <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-flag"></i></span>
                                    <select class="form-select" name="priority" required>
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                        <option value="Critical">Critical</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reported Source <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-headset"></i></span>
                                    <select class="form-select" name="source" required>
                                        <option value="Walk-in" selected>Walk-in</option>
                                        <option value="Phone">Phone</option>
                                        <option value="Email">Email</option>
                                        <option value="Portal">Portal</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 mt-4">
                            <label class="form-label">Issue Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" placeholder="Brief summary of the issue" required maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Detailed explanation of the issue reported..." required></textarea>
                        </div>

                        <div class="mb-2 mt-4">
                            <label class="form-label">Attachment (Optional)</label>
                            <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.docx">
                            <div class="form-text text-muted" style="font-size: 0.75rem;"><i class="fa-solid fa-circle-info"></i> Allowed: JPG, PNG, PDF, DOCX (Max: 5MB)</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0">
                        <button type="button" class="btn btn-light border fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand"><i class="fa-solid fa-paper-plane me-2"></i>Create Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="toast"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

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
        
        document.addEventListener('DOMContentLoaded', () => {
            setupLayoutObserver();

            // Initialize Tom Select for Departments
            var deptSelect = new TomSelect("#department_select", {
                create: true, // Allow IT admin to type a new department if it isn't listed
                sortField: { field: "text", direction: "asc" }
            });

            // Initialize Tom Select for Employees
            var empSelect = new TomSelect("#employee_select", {
                create: false,
                sortField: { field: "text", direction: "asc" },
                onChange: function(value) {
                    if (!value) return;
                    // Auto-fill logic: get the data-dept from the selected option
                    var opt = document.querySelector(`#employee_select option[value="${value}"]`);
                    if (opt && opt.dataset.dept) {
                        deptSelect.setValue(opt.dataset.dept);
                    }
                }
            });
        });

        // Live Filtering Logic
        function filterTickets() {
            const searchInput = document.getElementById('searchTicket').value.toLowerCase();
            const categoryFilter = document.getElementById('filterCategory').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            
            const rows = document.querySelectorAll('.ticket-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const ticketId = row.querySelector('.ticket-id').innerText.toLowerCase();
                const subject = row.querySelector('.ticket-subject').innerText.toLowerCase();
                const rowCategory = row.getAttribute('data-category').toLowerCase();
                const rowStatus = row.getAttribute('data-status').toLowerCase();

                const matchesSearch = ticketId.includes(searchInput) || subject.includes(searchInput);
                const matchesCategory = categoryFilter === 'all' || rowCategory === categoryFilter;
                const matchesStatus = statusFilter === 'all' || rowStatus === statusFilter;

                if (matchesSearch && matchesCategory && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('visibleCount').innerText = visibleCount;
        }

        // Toast Notification System
        function showToast(message, type) {
            const toast = document.getElementById("toast");
            toast.innerText = message;
            toast.className = "show " + type;
            setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3500);
        }

        const toastMsg = "<?php echo $toast_msg; ?>";
        const toastType = "<?php echo $toast_type; ?>";
        if (toastMsg) {
            showToast(toastMsg, toastType);
        }
    </script>

</body>
</html>