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

// 3. FETCH TICKETS FROM DATABASE
$tickets = [];
$departments = []; // To hold unique departments for the dynamic filter

$query = "SELECT t.*, COALESCE(ep.full_name, u.username, 'Unknown User') as requester_name 
          FROM tickets t 
          LEFT JOIN users u ON t.user_id = u.id 
          LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
          ORDER BY t.created_at DESC";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
        // Collect unique departments for the dropdown filter
        if (!empty($row['department']) && !in_array($row['department'], $departments)) {
            $departments[] = $row['department'];
        }
    }
}

// Helper function for Priority Badge Colors
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

// Helper function for Status Badge Colors
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
    <title>Manage Tickets - IT Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: 0.2s;
        }
        .btn-brand:hover { background-color: var(--primary-light); color: white; }

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

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
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
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-2x mb-3 text-secondary opacity-50"></i>
                                        <p class="mb-0">No tickets found in the system.</p>
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
                                        <td class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['requester_name']); ?></td>
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
    </script>

</body>
</html>