<?php
// -------------------------------------------------------------------------
// PAGE: IT Executive - Admin Ticket Assignment (Mock Data Version)
// -------------------------------------------------------------------------
ob_start(); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Fix Timezone
date_default_timezone_set('Asia/Kolkata');

// -------------------------------------------------------------------------
// 1. MOCK DATA (Simulating Admin Assignments without Database)
// -------------------------------------------------------------------------
$admin_assigned_tickets = [
    [
        'id' => 1,
        'admin_ticket_id' => 'TKT-99201', 
        'subject' => 'Laptop Screen Flickering', 
        'raised_by' => 'Vasanth (Employee)',
        'priority' => 'High', 
        'assigned_at' => '2026-02-21 09:15:00',
        'description' => 'The screen flickers whenever I open Android Studio. It stops when I restart, but comes back after 10 minutes.',
        'admin_note' => 'Needs display replacement check. Stephen, please verify if it is under warranty.',
        'status' => 'New'
    ],
    [
        'id' => 2,
        'admin_ticket_id' => 'TKT-48241', 
        'subject' => 'Server Connectivity Failure', 
        'raised_by' => 'Priya (HR)',
        'priority' => 'Critical', 
        'assigned_at' => '2026-02-21 10:30:00',
        'description' => 'Unable to access the local HRMS server from the Finance department floor.',
        'admin_note' => 'Check Rack B cooling fans and network switch status immediately.',
        'status' => 'In Progress'
    ],
    [
        'id' => 3,
        'admin_ticket_id' => 'TKT-88402', 
        'subject' => 'Bulk Software Installation', 
        'priority' => 'Low', 
        'assigned_at' => '2026-02-20 14:00:00',
        'description' => 'Install VS Code, Docker, and Postman on 5 new developer workstations.',
        'admin_note' => 'You can use the automated script located in the shared IT folder.',
        'status' => 'Pending'
    ]
];

// Existing Header and Sidebar Inclusion
include('../header.php'); 
include('../sidebars.php'); 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    :root {
        --brand-teal: #0d9488;
        --bg-slate: #f8fafc;
        --border-slate: #e2e8f0;
    }

    body { background-color: var(--bg-slate); font-family: 'Inter', sans-serif; color: #334155; }
    
    #mainContent { margin-left: 95px; padding: 30px; transition: all 0.3s ease; min-height: 100vh; }
    #mainContent.main-shifted { margin-left: 315px; }

    .header-box { margin-bottom: 2rem; }
    .card { background: white; border-radius: 1rem; border: 1px solid var(--border-slate); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 1.5rem; }
    
    /* Ticket Grid UI */
    .ticket-card { border-bottom: 1px solid #f1f5f9; padding: 1.5rem; transition: all 0.2s; }
    .ticket-card:hover { background: #fdfdfd; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
    .ticket-card:last-child { border-bottom: none; }

    .badge-prio { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
    .bg-high, .bg-critical { background: #fee2e2; color: #dc2626; }
    .bg-medium { background: #fff7ed; color: #f59e0b; }
    .bg-low { background: #f0fdf4; color: #16a34a; }

    .status-pill { font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 6px; }
    .st-new { background: #eff6ff; color: #2563eb; }
    .st-process { background: #fef9c3; color: #a16207; }
    .st-pending { background: #f5f5f5; color: #737373; }

    .admin-note-box { background: #f8fafc; border-left: 4px solid var(--brand-teal); padding: 1rem; margin: 1.2rem 0; border-radius: 4px; }
    
    .btn-action { background: var(--brand-teal); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; }
    .btn-action:hover { background: #0f766e; color: white; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3); }

    .search-input { border: 1px solid var(--border-slate); border-radius: 8px; padding: 8px 15px; width: 300px; font-size: 0.85rem; }
</style>

<div id="mainContent">
    <div class="dashboard-container">
        
        <div class="header-box d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-bold text-dark m-0">Admin Ticket Inbox</h1>
                <p class="text-muted small">Manage work assignments forwarded from the IT Admin</p>
            </div>
            <div class="d-flex gap-3">
                <input type="text" class="search-input" placeholder="Search ticket ID...">
                <div class="bg-white p-2 border rounded shadow-sm">
                    <i data-lucide="calendar" class="w-4 inline text-teal-600"></i>
                    <span class="fw-bold small"><?php echo date('d M, Y'); ?></span>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card p-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); color: white;">
                    <p class="text-xs font-bold opacity-80 uppercase mb-1">New Assignments</p>
                    <h2 class="fw-bold m-0">03</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 shadow-sm border-0 bg-white">
                    <p class="text-xs font-bold text-slate-400 uppercase mb-1">In Progress</p>
                    <h2 class="fw-bold m-0 text-primary">02</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 shadow-sm border-0 bg-white">
                    <p class="text-xs font-bold text-slate-400 uppercase mb-1">Avg Completion Time</p>
                    <h2 class="fw-bold m-0 text-warning">4.2 Hrs</h2>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0 text-slate-700">Assignment Queue</h5>
                <button class="btn btn-sm btn-light border text-xs font-bold">Sort by Priority</button>
            </div>
            <div class="card-body p-0">
                <?php foreach($admin_assigned_tickets as $ticket): ?>
                    <div class="ticket-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-uppercase text-muted fw-bold" style="font-size: 10px;">Ticket ID: #<?php echo $ticket['admin_ticket_id']; ?></span>
                                <h4 class="fw-bold text-slate-800 m-0 mt-1"><?php echo $ticket['subject']; ?></h4>
                                <div class="d-flex align-items-center gap-3 mt-2">
                                    <span class="small text-muted"><i data-lucide="user" class="w-3 inline"></i> <?php echo $ticket['raised_by']; ?></span>
                                    <span class="small text-muted"><i data-lucide="clock" class="w-3 inline"></i> <?php echo date('h:i A', strtotime($ticket['assigned_at'])); ?></span>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge-prio bg-<?php echo strtolower($ticket['priority']); ?>"><?php echo $ticket['priority']; ?></span>
                                <div class="mt-2">
                                    <?php 
                                        $sClass = ($ticket['status'] == 'New') ? 'new' : (($ticket['status'] == 'Pending') ? 'pending' : 'process'); 
                                    ?>
                                    <span class="status-pill st-<?php echo $sClass; ?>"><?php echo $ticket['status']; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="admin-note-box">
                            <span class="text-xs fw-bold text-teal-700 uppercase"><i data-lucide="user-check" class="w-3 inline"></i> Admin Instruction:</span>
                            <p class="m-0 mt-1 italic small text-slate-600">"<?php echo $ticket['admin_note']; ?>"</p>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="#" class="btn-action">
                                <i data-lucide="play-circle" class="w-4"></i> Start Working
                            </a>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3">View History</button>
                                <button class="btn btn-sm btn-outline-teal rounded-pill px-3" style="color:var(--brand-teal); border-color:var(--brand-teal);">Notify Admin</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
    // Initialize Lucide Icons
    lucide.createIcons();
</script>

<?php ob_end_flush(); ?>