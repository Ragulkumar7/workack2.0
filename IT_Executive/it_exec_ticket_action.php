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
        
        /* Professional Palette */
        --brand-color: #1b5a5a;
        --brand-hover: #164848;
        --brand-soft: rgba(27, 90, 90, 0.08);
        
        --text-main: #344767;
        --text-secondary: #7b809a;
        --text-light: #999999;
        
        --bg-body: #f0f2f5;
        --bg-card: #ffffff;
        --border-color: #e2e8f0;
        
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', 'Segoe UI', sans-serif;
        color: var(--text-main);
        -webkit-font-smoothing: antialiased;
        overflow-x: hidden;
    }

    /* --- LAYOUT CONTAINER --- */
    #mainContent {
        margin-left: var(--primary-width);
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 40px;
        min-height: 100vh;
    }

    #mainContent.main-shifted {
        margin-left: calc(var(--primary-width) + var(--secondary-width));
    }

    @media (max-width: 991px) {
        #mainContent, #mainContent.main-shifted {
            margin-left: 0;
            padding: 20px;
        }
    }

    /* --- PAGE HEADER --- */
    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-main);
        letter-spacing: -0.5px;
    }
    
    .meta-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 20px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 5px;
    }

    /* --- PROFESSIONAL ACCORDION CARDS --- */
    .section-card {
        background: var(--bg-card);
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: 16px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .section-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    /* Header Styling */
    .section-header {
        padding: 20px 24px;
        background: #fff;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid transparent;
        transition: all 0.2s ease;
    }

    .section-header:hover {
        background-color: #fafafa;
    }

    .section-header.active {
        background-color: #fff;
        border-bottom: 1px solid var(--border-color);
    }
    
    /* Active indicator line on the left */
    .section-header.active::before {
        content: '';
        position: absolute;
        left: 0;
        width: 4px;
        height: 24px;
        background-color: var(--brand-color);
        border-radius: 0 4px 4px 0;
    }

    .section-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .icon-box {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background-color: var(--brand-soft);
        color: var(--brand-color);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
    }

    .section-header:hover .icon-box {
        background-color: rgba(27, 90, 90, 0.15);
    }

    /* Body Animation */
    .section-body {
        display: none;
        padding: 30px;
        background-color: #fff;
        opacity: 0;
    }

    .section-body.show {
        display: block;
        animation: fadeInSlide 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* --- FORM ELEMENTS --- */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 0.95rem;
        color: var(--text-main);
        background-color: #fcfcfc;
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--brand-color);
        background-color: #fff;
        box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1);
        outline: none;
    }

    .form-control::placeholder {
        color: #cbd5e0;
    }

    /* --- INFO DISPLAY --- */
    .info-group {
        margin-bottom: 20px;
    }
    
    .info-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        font-weight: 700;
        margin-bottom: 6px;
    }

    .info-value {
        font-size: 1rem;
        font-weight: 500;
        color: var(--text-main);
    }

    .category-crumb {
        display: inline-flex;
        align-items: center;
        background: #f8fafc;
        padding: 8px 16px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .admin-alert {
        background-color: #fff8e1;
        border: 1px solid #ffeeba;
        color: #b45309;
        padding: 16px;
        border-radius: 12px;
        display: flex;
        gap: 12px;
        font-size: 0.95rem;
    }

    .inventory-box {
        background-color: #f1f8f8;
        border: 1px dashed var(--brand-color);
        border-radius: 12px;
        padding: 20px;
    }

    /* --- BUTTONS --- */
    .btn-back {
        background: #fff;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }

    .btn-back:hover {
        background: #f8fafc;
        color: var(--text-main);
        transform: translateX(-2px);
    }

    .btn-brand {
        background: linear-gradient(135deg, var(--brand-color) 0%, #134242 100%);
        border: none;
        color: #fff;
        padding: 12px 32px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 1rem;
        box-shadow: 0 4px 6px rgba(27, 90, 90, 0.2);
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-brand:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(27, 90, 90, 0.3);
        color: #fff;
    }

    /* Chevron Animation */
    .toggle-icon {
        color: var(--text-light);
        transition: transform 0.3s ease;
    }
    
    .section-header.active .toggle-icon {
        transform: rotate(180deg);
        color: var(--brand-color);
    }
</style>

<div id="mainContent">
    <div class="container-fluid p-0" style="max-width: 1200px; margin: 0 auto;">
        
        <div class="d-flex justify-content-between align-items-end mb-5">
            <div>
                <div class="meta-badge mb-2">
                    <i data-lucide="clock" style="width: 14px; margin-right: 6px;"></i>
                    Raised: <?php echo $date_raised; ?>
                </div>
                <h1 class="page-title">Work Order #<?php echo $ticket_id; ?></h1>
            </div>
            <a href="it_exec_ticket_list.php" class="btn-back">
                <i data-lucide="arrow-left" style="width: 18px;"></i> Back to List
            </a>
        </div>

        <form action="submit_resolution.php" method="POST">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

            <div class="section-card">
                <div class="section-header" onclick="toggleSection('requesterProfile', this)">
                    <div class="section-title">
                        <div class="icon-box">
                            <i data-lucide="user" style="width: 20px;"></i>
                        </div>
                        Requester Profile
                    </div>
                    <i data-lucide="chevron-down" class="toggle-icon"></i>
                </div>
                <div id="requesterProfile" class="section-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-group">
                                <div class="info-label">Name</div>
                                <div class="info-value"><?php echo $raised_by; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <div class="info-label">Role</div>
                                <div class="info-value"><?php echo $designation; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo $department; ?></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="info-group mb-0">
                                <div class="info-label">Contact / Extension</div>
                                <div class="info-value text-muted">Not Provided</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header" onclick="toggleSection('issueDetails', this)">
                    <div class="section-title">
                        <div class="icon-box">
                            <i data-lucide="alert-circle" style="width: 20px;"></i>
                        </div>
                        Issue Specification
                    </div>
                    <i data-lucide="chevron-down" class="toggle-icon"></i>
                </div>
                <div id="issueDetails" class="section-body">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="info-label mb-2">Category Path</div>
                            <div class="category-crumb">
                                <span><?php echo $main_category; ?></span>
                                <i data-lucide="chevron-right" style="width: 14px; margin: 0 10px; color: #cbd5e0;"></i>
                                <span><?php echo $sub_category_1; ?></span>
                                <i data-lucide="chevron-right" style="width: 14px; margin: 0 10px; color: #cbd5e0;"></i>
                                <span style="color: #e53e3e; font-weight: 600;"><?php echo $sub_category_2; ?></span>
                            </div>
                        </div>
                        <div class="col-md-12 mb-4">
                            <div class="info-label mb-2">Problem Description</div>
                            <div class="p-3 bg-light border rounded" style="color: var(--text-main); line-height: 1.6;">
                                <?php echo $issue_description; ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="admin-alert">
                                <i data-lucide="bell-ring" style="width: 20px; flex-shrink: 0; color: #d97706;"></i>
                                <div>
                                    <strong style="color: #92400e; display: block; margin-bottom: 4px;">Admin Instruction</strong>
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
                        <div class="icon-box" style="background-color: var(--brand-color); color: white;">
                            <i data-lucide="wrench" style="width: 18px;"></i>
                        </div>
                        Technician Resolution Console
                    </div>
                    <i data-lucide="chevron-down" class="toggle-icon"></i>
                </div>
                <div id="techConsole" class="section-body show">
                    
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
                            <input type="text" class="form-control" value="<?php echo $priority; ?>" readonly style="background-color: #f1f5f9;">
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px; color: var(--text-secondary); font-weight: 700; margin-bottom: 15px;">Technical Diagnosis</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Root Cause Analysis</label>
                            <textarea class="form-control" name="diagnosis" rows="2" placeholder="Identify the root cause of the issue..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Solution Provided <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="solution" rows="4" required placeholder="Detail the steps taken to resolve the issue..."></textarea>
                        </div>
                    </div>

                    <div class="inventory-box mb-4">
                        <label class="form-label" style="color: var(--brand-color); display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="package" style="width: 16px;"></i> Inventory Usage (Optional)
                        </label>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <input type="text" class="form-control" name="part_name" placeholder="Item Name / Description" style="background-color: #fff;">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control" name="part_serial" placeholder="Serial No. / Asset ID" style="background-color: #fff;">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Time Spent</label>
                            <input type="text" class="form-control" name="time_taken" placeholder="e.g. 1h 30m">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Completion Date</label>
                            <input type="date" class="form-control" name="completion_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="text-end border-top pt-4">
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

    function toggleSection(id, header) {
        // Toggle the body visibility
        const body = document.getElementById(id);
        body.classList.toggle('show');
        
        // Toggle active styling on header
        header.classList.toggle('active');
    }
</script>

<?php 
// Close Output Buffer
ob_end_flush(); 
?>