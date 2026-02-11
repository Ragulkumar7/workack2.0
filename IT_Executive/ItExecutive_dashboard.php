<?php
// -------------------------------------------------------------------------
// PAGE: IT Executive Dashboard (Executive Overview Style)
// -------------------------------------------------------------------------
ob_start(); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// -------------------------------------------------------------------------
// 1. MOCK DATA
// -------------------------------------------------------------------------
$exec_name = "John Doe";
$current_date = date('d M, Y');

// Stats Data
$pending_count = 5;
$inprogress_count = 2;
$completed_today = 4;
$total_tickets = $pending_count + $inprogress_count + $completed_today;

// High Priority Tasks
$priority_tasks = [
    ['id' => 'IT-2026-901', 'issue' => 'Server Connectivity Failure', 'dept' => 'Finance', 'time' => '10 mins ago'],
    ['id' => 'IT-2026-899', 'issue' => 'CEO Laptop Crash', 'dept' => 'Management', 'time' => '1 hour ago']
];

// Recent Tickets
$recent_tickets = [
    ['id' => 'IT-2026-884', 'issue' => 'Blue Screen Error', 'raised_by' => 'Priya (HR)', 'status' => 'In Progress', 'priority' => 'High'],
    ['id' => 'IT-2026-880', 'issue' => 'Printer Jam', 'raised_by' => 'Rahul (Sales)', 'status' => 'Pending', 'priority' => 'Medium'],
    ['id' => 'IT-2026-875', 'issue' => 'Outlook not syncing', 'raised_by' => 'Sarah (Ops)', 'status' => 'Pending', 'priority' => 'Low'],
    ['id' => 'IT-2026-870', 'issue' => 'Install PowerBI', 'raised_by' => 'Vikram (Data)', 'status' => 'Resolved', 'priority' => 'Medium']
];

include('../header.php'); 
include('../sidebars.php'); 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    :root {
        --primary-width: 95px;
        --secondary-width: 220px;
        --brand-color: #1b5a5a;
        --text-main: #344767;
        --text-muted: #7b809a;
        --bg-body: #f8fafc;
        --card-bg: #ffffff;
        --border: #e2e8f0;
        --pending: #f59e0b;
        --progress: #3b82f6;
        --completed: #10b981;
        --neutral: #64748b;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', sans-serif;
        color: var(--text-main);
        overflow-x: hidden;
    }

    /* --- LAYOUT --- */
    #mainContent {
        margin-left: var(--primary-width);
        transition: margin-left 0.3s ease;
        padding: 30px;
        min-height: 100vh;
    }
    #mainContent.main-shifted { margin-left: calc(var(--primary-width) + var(--secondary-width)); }
    
    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }

    @media (max-width: 991px) {
        #mainContent, #mainContent.main-shifted { margin-left: 0; padding: 20px; }
    }

    /* --- HEADER --- */
    .page-header {
        margin-bottom: 30px;
    }
    .header-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 4px;
    }
    .header-sub {
        font-size: 0.95rem;
        color: var(--text-muted);
    }

    /* ────────────────────────────────────────────────
       IMPROVED STATUS CARDS (like modern dashboard style)
    ──────────────────────────────────────────────── */
    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .status-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.75rem 1.5rem;
        border: 1px solid var(--border);
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
    }

    .status-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }

    .card-header-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .card-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-muted);
    }

    .card-icon {
        font-size: 1.5rem;
        color: var(--neutral);
    }

    .card-number {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 0.5rem;
        color: #1e293b;
    }

    .card-trend {
        font-size: 0.875rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .trend-up { color: var(--completed); }
    .trend-down { color: #ef4444; }
    .trend-neutral { color: var(--neutral); }

    /* Status-specific colors */
    .card-pending .card-number   { color: var(--pending); }
    .card-progress .card-number  { color: var(--progress); }
    .card-completed .card-number { color: var(--completed); }
    .card-total .card-number     { color: var(--brand-color); }

    /* --- PRIORITY ALERT & TABLE (unchanged) --- */
    .priority-section {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        margin-bottom: 24px;
    }
    .priority-title { font-weight: 600; color: #b91c1c; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
    
    .priority-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 16px; background: #fef2f2; border: 1px solid #fee2e2;
        border-radius: 8px; margin-bottom: 8px;
    }

    .table-container {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .table-title { font-weight: 600; color: #334155; margin-bottom: 20px; }

    .custom-table th { background: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
    .custom-table td { padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.9rem; }
    
    .badge-status { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
    .st-pending { background: #fff7ed; color: #c2410c; }
    .st-progress { background: #eff6ff; color: #1d4ed8; }
    .st-resolved { background: #ecfdf5; color: #047857; }

    .btn-action { color: #1b5a5a; background: rgba(27, 90, 90, 0.08); padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; }
</style>

<div id="mainContent">
    <div class="dashboard-container">
        
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="header-title">Executive Overview</h1>
                <div class="header-sub">Snapshot of assigned tickets and performance</div>
            </div>
            <div style="font-size:0.9rem; color:#64748b; font-weight:500;">
                <i data-lucide="calendar" style="width:14px; margin-right:5px;"></i> <?php echo $current_date; ?>
            </div>
        </div>

        <!-- Updated Status Cards Grid -->
        <div class="status-grid">

            <div class="status-card card-total">
                <div class="card-header-row">
                    <span class="card-label">Total Assigned</span>
                    <i data-lucide="layers" class="card-icon"></i>
                </div>
                <div class="card-number"><?php echo $total_tickets; ?></div>
                <div class="card-trend trend-neutral">
                    <i data-lucide="minus" style="width:14px;"></i> No new items
                </div>
            </div>

            <div class="status-card card-pending">
                <div class="card-header-row">
                    <span class="card-label">Pending Tasks</span>
                    <i data-lucide="clock" class="card-icon"></i>
                </div>
                <div class="card-number"><?php echo $pending_count; ?></div>
                <div class="card-trend trend-down">
                    <i data-lucide="trending-up" style="width:14px;"></i> +<?php echo rand(2,8); ?>% this week
                </div>
            </div>

            <div class="status-card card-progress">
                <div class="card-header-row">
                    <span class="card-label">In Progress</span>
                    <i data-lucide="loader-2" class="card-icon"></i>
                </div>
                <div class="card-number"><?php echo $inprogress_count; ?></div>
                <div class="card-trend trend-neutral">
                    <i data-lucide="activity" style="width:14px;"></i> Active now
                </div>
            </div>

            <div class="status-card card-completed">
                <div class="card-header-row">
                    <span class="card-label">Completed Today</span>
                    <i data-lucide="check-circle" class="card-icon"></i>
                </div>
                <div class="card-number"><?php echo $completed_today; ?></div>
                <div class="card-trend trend-up">
                    <i data-lucide="trending-up" style="width:14px;"></i> +<?php echo rand(10,25); ?>%
                </div>
            </div>

        </div>

        <?php if(!empty($priority_tasks)): ?>
        <div class="priority-section">
            <div class="priority-title"><i data-lucide="alert-triangle" style="width:18px;"></i> High Priority Attention</div>
            <?php foreach($priority_tasks as $ptask): ?>
            <div class="priority-item">
                <div>
                    <div style="font-weight:600; color:#1f2937; font-size:0.95rem;"><?php echo $ptask['issue']; ?></div>
                    <div style="font-size:0.85rem; color:#6b7280;"><?php echo $ptask['dept']; ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span style="font-size:0.85rem; color:#dc2626; font-weight:600;"><?php echo $ptask['time']; ?></span>
                    <a href="it_exec_ticket_action.php?id=<?php echo $ptask['id']; ?>" class="btn-action" style="color:#dc2626; background:#fee2e2;">Fix Now</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-title">My Assigned Tickets</div>
            <div class="table-responsive">
                <table class="table custom-table mb-0">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Issue</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_tickets as $ticket): ?>
                        <tr>
                            <td style="font-weight:600; color:var(--brand-color);"><?php echo $ticket['id']; ?></td>
                            <td><?php echo $ticket['issue']; ?></td>
                            <td>
                                <?php 
                                    $statusClass = 'st-pending';
                                    if($ticket['status'] == 'In Progress') $statusClass = 'st-progress';
                                    if($ticket['status'] == 'Resolved') $statusClass = 'st-resolved';
                                ?>
                                <span class="badge-status <?php echo $statusClass; ?>"><?php echo $ticket['status']; ?></span>
                            </td>
                            <td class="text-end">
                                <a href="it_exec_ticket_action.php?id=<?php echo $ticket['id']; ?>" class="btn-action">Open</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    lucide.createIcons();
</script>

<?php ob_end_flush(); ?>