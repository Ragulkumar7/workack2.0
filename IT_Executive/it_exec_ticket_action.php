<?php
// -------------------------------------------------------------------------
// PAGE: Executive Task / Ticket Resolution
// -------------------------------------------------------------------------
// FIX: Start Output Buffering immediately to prevent header errors
ob_start(); 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------------------
// 1. MOCK DATA (Replace with SQL SELECT query)
// -------------------------------------------------------------------------
$ticket_id = "IT-2026-884";
$raised_by = "Priya Sharma";
$designation = "HR Manager"; 
$department = "Human Resources";
$date_raised = "11-Feb-2026 09:15 AM";
$priority = "High";

// Admin Note
$admin_note = "Priority issue. Payroll system crashing. Check RAM and OS logs immediately.";

// Category Info
$main_category = "Internal Team"; 
$sub_category_1 = "System Admin"; 
$sub_category_2 = "System Problem"; 
$issue_description = "When I try to export the salary sheet, the system freezes and shows a blue screen error. This needs to be fixed before EOD.";

// -------------------------------------------------------------------------
// 2. INCLUDES
// -------------------------------------------------------------------------
include('../header.php'); 
include('../sidebars.php'); 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    :root {
        /* Layout Variables */
        --primary-width: 95px;
        --secondary-width: 220px;
        
        /* Brand Colors */
        --brand-color: #1b5a5a;
        --brand-hover: #134242;
        --bg-body: #f3f4f6;
        --text-main: #344767;
        --text-secondary: #7b809a;
        --border-color: #e2e8f0;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', 'Segoe UI', sans-serif;
        color: var(--text-main);
        overflow-x: hidden;
    }

    /* --- LAYOUT CONTAINER --- */
    #mainContent {
        margin-left: var(--primary-width);
        transition: margin-left 0.3s ease;
        padding: 30px;
        min-height: 100vh;
    }

    #mainContent.main-shifted {
        margin-left: calc(var(--primary-width) + var(--secondary-width));
    }

    @media (max-width: 991px) {
        #mainContent, #mainContent.main-shifted {
            margin-left: 0;
            padding: 15px;
        }
    }

    /* --- HEADER STYLES --- */
    .page-header {
        margin-bottom: 25px;
    }
    .ticket-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-main);
    }
    .ticket-meta {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    .btn-back {
        background: #fff;
        border: 1px solid var(--border-color);
        padding: 8px 16px;
        border-radius: 8px;
        color: var(--text-secondary);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-back:hover {
        background: #f8fafc;
        color: var(--text-main);
    }

    /* --- TAB NAVIGATION (The "Drill-Down" Look) --- */
    .tabs-container {
        display: flex;
        gap: 30px;
        border-bottom: 1px solid #e0e0e0;
        margin-bottom: 0; /* Connected to card */
        padding-left: 10px;
    }

    .tab-item {
        padding: 12px 5px;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-secondary);
        cursor: pointer;
        position: relative;
        transition: color 0.3s;
    }

    .tab-item:hover {
        color: var(--brand-color);
    }

    .tab-item.active {
        color: var(--brand-color);
    }

    /* The blue active indicator bar */
    .tab-item.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 3px;
        background-color: var(--brand-color);
        border-radius: 3px 3px 0 0;
    }

    /* --- CONTENT CARD --- */
    .content-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        padding: 30px;
        min-height: 400px;
        margin-top: 20px; /* Space between tabs and content */
        border: 1px solid rgba(0,0,0,0.04);
    }

    .tab-pane {
        display: none;
        animation: fadeIn 0.3s ease-in-out;
    }
    .tab-pane.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* --- CONTENT STYLING --- */
    .info-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        font-weight: 700;
        margin-bottom: 6px;
        letter-spacing: 0.5px;
    }
    .info-value {
        font-size: 1rem;
        font-weight: 500;
        color: var(--text-main);
        margin-bottom: 20px;
    }
    
    .category-crumb {
        display: inline-flex;
        align-items: center;
        background: #f8fafc;
        padding: 10px 15px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .admin-alert {
        background-color: #fff8e1;
        border-left: 4px solid #f59e0b;
        padding: 15px;
        border-radius: 4px;
        color: #92400e;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    /* Form Styles */
    .form-label {
        font-weight: 600;
        color: var(--text-main);
        font-size: 0.9rem;
        margin-bottom: 8px;
    }
    .form-control, .form-select {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 0.95rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--brand-color);
        box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1);
        outline: none;
    }

    .btn-brand {
        background-color: var(--brand-color);
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-brand:hover {
        background-color: #134242;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(27, 90, 90, 0.2);
    }
</style>

<div id="mainContent">
    <div class="container-fluid p-0" style="max-width: 1400px;">
        
        <div class="d-flex justify-content-between align-items-center page-header">
            <div>
                <h1 class="ticket-title">Work Order #<?php echo $ticket_id; ?></h1>
                <div class="ticket-meta">Raised on <?php echo $date_raised; ?></div>
            </div>
            <a href="it_exec_ticket_list.php" class="btn-back">
                <i data-lucide="arrow-left" style="width: 18px;"></i> Back to List
            </a>
        </div>

        <form action="submit_resolution.php" method="POST">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

            <div class="tabs-container">
                <div class="tab-item active" onclick="switchTab('tab-requester', this)">Requester Profile</div>
                <div class="tab-item" onclick="switchTab('tab-issue', this)">Issue Specification</div>
                <div class="tab-item" onclick="switchTab('tab-resolution', this)">Technician Resolution Console</div>
            </div>

            <div class="content-card">
                
                <div id="tab-requester" class="tab-pane active">
                    <h5 class="mb-4 fw-bold text-dark border-bottom pb-2">User Information</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?php echo $raised_by; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Role</div>
                            <div class="info-value"><?php echo $designation; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?php echo $department; ?></div>
                        </div>
                        <div class="col-md-12">
                            <div class="info-label">Contact / Extension</div>
                            <div class="info-value text-muted">Not Provided</div>
                        </div>
                    </div>
                </div>

                <div id="tab-issue" class="tab-pane">
                    <h5 class="mb-4 fw-bold text-dark border-bottom pb-2">Problem Details</h5>
                    
                    <div class="mb-4">
                        <div class="info-label mb-2">Category Path</div>
                        <div class="category-crumb">
                            <span><?php echo $main_category; ?></span>
                            <i data-lucide="chevron-right" style="width: 14px; margin: 0 10px;"></i>
                            <span><?php echo $sub_category_1; ?></span>
                            <i data-lucide="chevron-right" style="width: 14px; margin: 0 10px;"></i>
                            <span class="text-danger fw-bold"><?php echo $sub_category_2; ?></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="info-label mb-2">Description</div>
                        <div class="p-3 bg-light border rounded" style="line-height: 1.6;">
                            <?php echo $issue_description; ?>
                        </div>
                    </div>

                    <div class="admin-alert">
                        <i data-lucide="bell" style="width: 20px;"></i>
                        <div>
                            <strong>Admin Instruction:</strong> <?php echo $admin_note; ?>
                        </div>
                    </div>
                </div>

                <div id="tab-resolution" class="tab-pane">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                        <h5 class="mb-0 fw-bold text-dark">Action Area</h5>
                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Status: Pending Action</span>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Update Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                <option value="in_progress" selected>In Progress</option>
                                <option value="waiting_parts">Waiting for Parts</option>
                                <option value="completed">Resolved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <input type="text" class="form-control" value="<?php echo $priority; ?>" readonly style="background-color: #f8f9fa;">
                        </div>
                    </div>

                    <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem;">Technical Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Root Cause Analysis</label>
                        <textarea class="form-control" name="diagnosis" rows="2" placeholder="What caused the issue?"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Solution Provided <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="solution" rows="4" required placeholder="Describe steps taken to resolve..."></textarea>
                    </div>

                    <div class="p-4 mb-4 rounded" style="background-color: #f1f8f8; border: 1px dashed var(--brand-color);">
                        <label class="fw-bold mb-3" style="color: var(--brand-color); display:flex; align-items:center; gap:8px;">
                            <i data-lucide="package" style="width: 16px;"></i> Inventory Usage (Optional)
                        </label>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <input type="text" class="form-control bg-white" name="part_name" placeholder="Item Name">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control bg-white" name="part_serial" placeholder="Serial No.">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Time Spent</label>
                            <input type="text" class="form-control" name="time_taken" placeholder="e.g. 30 Mins">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Completion Date</label>
                            <input type="date" class="form-control" name="completion_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="text-end pt-3 border-top">
                        <button type="submit" class="btn-brand">
                            <i data-lucide="check-circle" style="width: 18px;"></i> Update Ticket
                        </button>
                    </div>
                </div>

            </div>
        </form>

    </div>
</div>

<script>
    lucide.createIcons();

    function switchTab(tabId, element) {
        // 1. Hide all tab panes
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });

        // 2. Remove active class from all tabs
        document.querySelectorAll('.tab-item').forEach(item => {
            item.classList.remove('active');
        });

        // 3. Show selected pane
        document.getElementById(tabId).classList.add('active');

        // 4. Set active class on clicked tab
        element.classList.add('active');
    }
</script>

<?php 
// Close Output Buffer
ob_end_flush(); 
?>