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
    :root {
        /* Layout Variables */
        --primary-width: 95px;
        --secondary-width: 220px;
        
        /* Brand Colors */
        --brand-color: #1b5a5a;
        --brand-hover: #134242;
        --brand-light: #e8f1f1;
        --bg-body: #f8f9fa;
        --text-dark: #2c3e50;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        overflow-x: hidden;
    }

    /* --- DYNAMIC CONTENT CONTAINER --- */
    #mainContent {
        margin-left: var(--primary-width);
        transition: margin-left 0.3s ease;
        padding: 30px;
        min-height: 100vh;
        width: auto;
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

    /* --- ACCORDION & CARD STYLING --- */
    .section-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 20px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    }

    .section-header {
        background: #fff;
        padding: 15px 20px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid transparent;
        transition: all 0.2s;
    }

    .section-header:hover {
        background-color: #fcfcfc;
    }

    .section-header.active {
        background-color: var(--brand-light);
        border-bottom: 1px solid #dceaea;
        color: var(--brand-color);
    }

    .section-title {
        font-weight: 600;
        font-size: 1.05rem;
        margin: 0;
        display: flex;
        align-items: center;
    }

    .section-body {
        padding: 25px;
        display: none; /* Hidden by default */
        background: #fff;
        border-top: 1px solid #f0f0f0;
    }

    /* Open state for JS toggle */
    .section-body.show {
        display: block;
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Form & UI Elements */
    .btn-brand {
        background-color: var(--brand-color);
        border-color: var(--brand-color);
        color: #fff;
        padding: 10px 30px;
        font-weight: 600;
        border-radius: 6px;
    }
    .btn-brand:hover {
        background-color: var(--brand-hover);
        color: #fff;
    }

    .info-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        color: #6c757d;
        font-weight: 700;
        margin-bottom: 5px;
        letter-spacing: 0.5px;
    }

    .info-data {
        font-size: 1rem;
        color: #212529;
        font-weight: 500;
    }

    .category-crumb {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        width: 100%;
        border: 1px solid #dee2e6;
    }
    
    .admin-alert {
        background-color: #fff3cd;
        border: 1px solid #ffecb5;
        color: #664d03;
        padding: 15px;
        border-radius: 6px;
        margin-top: 15px;
        display: flex;
        align-items: start;
    }

    .toggle-icon {
        transition: transform 0.3s;
    }
    .section-header.active .toggle-icon {
        transform: rotate(180deg);
    }
</style>

<div id="mainContent">
    <div class="container-fluid p-0">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1" style="color: var(--text-dark);">Work Order #<?php echo $ticket_id; ?></h3>
                <span class="text-muted">Raised on <?php echo $date_raised; ?></span>
            </div>
            <a href="it_exec_ticket_list.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center" style="height: 38px;">
                <i data-lucide="arrow-left" style="width: 16px; margin-right: 5px;"></i> Back to List
            </a>
        </div>

        <form action="submit_resolution.php" method="POST">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

            <div class="section-card">
                <div class="section-header" onclick="toggleSection('requesterProfile', this)">
                    <div class="section-title">
                        <i data-lucide="user" style="width: 20px; margin-right: 10px; color: var(--brand-color);"></i>
                        Requester Profile
                    </div>
                    <i data-lucide="chevron-down" class="toggle-icon"></i>
                </div>
                <div id="requesterProfile" class="section-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="info-label">Name</div>
                            <div class="info-data"><?php echo $raised_by; ?></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="info-label">Role</div>
                            <div class="info-data"><?php echo $designation; ?></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="info-label">Department</div>
                            <div class="info-data"><?php echo $department; ?></div>
                        </div>
                        <div class="col-md-12">
                            <div class="info-label">Contact / Extension</div>
                            <div class="info-data text-muted">Not Provided</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header" onclick="toggleSection('issueDetails', this)">
                    <div class="section-title">
                        <i data-lucide="alert-circle" style="width: 20px; margin-right: 10px; color: var(--brand-color);"></i>
                        Issue Specification
                    </div>
                    <i data-lucide="chevron-down" class="toggle-icon"></i>
                </div>
                <div id="issueDetails" class="section-body">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="info-label">Category Path</div>
                            <div class="category-crumb">
                                <span><?php echo $main_category; ?></span>
                                <i data-lucide="chevron-right" style="width: 14px; margin: 0 10px; color: #adb5bd;"></i>
                                <span><?php echo $sub_category_1; ?></span>
                                <i data-lucide="chevron-right" style="width: 14px; margin: 0 10px; color: #adb5bd;"></i>
                                <span class="text-danger fw-bold"><?php echo $sub_category_2; ?></span>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="info-label">Problem Description</div>
                            <div class="p-3 bg-light border rounded text-dark">
                                <?php echo $issue_description; ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="admin-alert">
                                <i data-lucide="bell" style="width: 18px; margin-right: 8px; margin-top: 2px;"></i>
                                <div>
                                    <strong>Admin Instruction:</strong><br>
                                    <?php echo $admin_note; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-card" style="border-top: 4px solid var(--brand-color);">
                <div class="section-header active" onclick="toggleSection('techConsole', this)">
                    <div class="section-title">
                        <i data-lucide="tool" style="width: 20px; margin-right: 10px; color: var(--brand-color);"></i>
                        Technician Resolution Console
                    </div>
                    <i data-lucide="chevron-down" class="toggle-icon"></i>
                </div>
                <div id="techConsole" class="section-body show">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Update Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                <option value="in_progress" selected>In Progress</option>
                                <option value="waiting_parts">Waiting for Parts</option>
                                <option value="completed">Resolved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Priority</label>
                            <input type="text" class="form-control bg-light" value="<?php echo $priority; ?>" readonly>
                        </div>
                    </div>

                    <h6 class="text-muted fw-bold text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Technical Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Root Cause Analysis</label>
                        <textarea class="form-control" name="diagnosis" rows="2" placeholder="What caused the issue?"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Solution Provided <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="solution" rows="4" required placeholder="Describe steps taken to resolve..."></textarea>
                    </div>

                    <div class="p-3 mb-4 rounded" style="background-color: #f0f7f7; border: 1px dashed var(--brand-color);">
                        <label class="fw-bold mb-2" style="color: var(--brand-color); font-size: 0.9rem;">
                            <i data-lucide="package" style="width: 14px; margin-right: 5px;"></i>Inventory Usage (Optional)
                        </label>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <input type="text" class="form-control" name="part_name" placeholder="Item Name">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control" name="part_serial" placeholder="Serial No.">
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

                    <div class="text-end">
                        <button type="submit" class="btn btn-brand btn-lg">
                            <i data-lucide="check-circle" style="width: 18px; margin-right: 5px;"></i> Update Ticket
                        </button>
                    </div>
                </div>
            </div>

        </form>

    </div>
</div>

<script>
    lucide.createIcons();

    function toggleSection(id, header) {
        // Toggle the body visibility
        const body = document.getElementById(id);
        body.classList.toggle('show');
        
        // Toggle active styling on header
        header.classList.toggle('active');
        
        // Optional: Close other sections if you want 'Accordion' style (only one open at a time)
        // To enable this, uncomment the lines below:
        /*
        const allBodies = document.querySelectorAll('.section-body');
        const allHeaders = document.querySelectorAll('.section-header');
        allBodies.forEach(el => { if(el.id !== id) el.classList.remove('show'); });
        allHeaders.forEach(el => { if(el !== header) el.classList.remove('active'); });
        */
    }
</script>

<?php 
// Close Output Buffer
ob_end_flush(); 
?>