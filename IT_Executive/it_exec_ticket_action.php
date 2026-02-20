<?php
// -------------------------------------------------------------------------
// PAGE: Executive Task / Ticket Resolution (Smart Auto-Load Version)
// -------------------------------------------------------------------------
ob_start(); 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Include Database Connection
include('../include/db_connect.php'); 

// --- SMART AUTO-LOAD LOGIC ---
$t_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($t_id === 0) {
    // If no ID is in the URL, automatically fetch the latest ticket for testing purposes
    $latest_query = $conn->query("SELECT id FROM tickets ORDER BY id DESC LIMIT 1");
    if ($latest_query && $latest_query->num_rows > 0) {
        $t_id = $latest_query->fetch_assoc()['id'];
    } else {
        die("<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>No tickets found in your database. Please create a ticket first.</h2></div>");
    }
}

// 2. Fetch Ticket and User details from database
$sql = "SELECT t.*, u.name AS raised_by_name, u.role AS user_designation, u.department AS user_dept 
        FROM tickets t 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $t_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>Ticket ID #$t_id not found.</h2><a href='it_exec_ticket_list.php'>Go Back</a></div>");
}

$ticket = $result->fetch_assoc();

// 3. Map DB values to Variables for UI (with safe fallbacks)
$ticket_code       = htmlspecialchars($ticket['ticket_code'] ?? 'N/A');
$raised_by         = htmlspecialchars($ticket['raised_by_name'] ?? 'Unknown User');
$designation       = htmlspecialchars($ticket['user_designation'] ?? 'Employee'); 
$department        = htmlspecialchars($ticket['department'] ?? 'IT');
$date_raised       = isset($ticket['created_at']) ? date("d-M-Y h:i A", strtotime($ticket['created_at'])) : 'N/A';
$priority          = htmlspecialchars($ticket['priority'] ?? 'Medium');
$issue_description = htmlspecialchars($ticket['description'] ?? 'No description provided.');
$subject           = htmlspecialchars($ticket['subject'] ?? 'General Issue');
$current_status    = htmlspecialchars($ticket['status'] ?? 'Open');

// Optional fields that might not exist yet
$admin_note = isset($ticket['admin_note']) ? htmlspecialchars($ticket['admin_note']) : "";

// Category Info 
$main_category  = $department; 
$sub_category_1 = "General Issue"; 
$sub_category_2 = $subject; 

// -------------------------------------------------------------------------
// 4. INCLUDES
// -------------------------------------------------------------------------
include('../header.php'); 
include('../sidebars.php'); 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    :root {
        --primary-width: 95px;
        --secondary-width: 220px;
        --brand-color: #1b5a5a;
        --brand-hover: #134242;
        --bg-body: #f8fafc;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border-light: #e2e8f0;
        --card-bg: #ffffff;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', sans-serif;
        color: var(--text-main);
    }

    #mainContent {
        margin-left: var(--primary-width);
        transition: margin-left 0.3s ease;
        padding: 20px 30px;
        min-height: 100vh;
    }

    .header-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 20px;
    }
    .page-title {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0 0 4px 0;
        letter-spacing: -0.5px;
    }
    .page-subtitle {
        color: var(--text-muted);
        font-size: 0.85rem;
        font-weight: 500;
    }
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: var(--card-bg);
        border: 1px solid var(--border-light);
        border-radius: 8px;
        color: var(--text-main);
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.2s;
    }
    .btn-back:hover {
        background: #f1f5f9;
        color: var(--brand-color);
    }

    .ticket-grid {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 20px;
        align-items: start;
    }

    @media (max-width: 1024px) {
        .ticket-grid { grid-template-columns: 1fr; }
    }

    .tk-card {
        background: var(--card-bg);
        border-radius: 12px;
        border: 1px solid var(--border-light);
        box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        margin-bottom: 20px;
    }
    .tk-card-header {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border-light);
        background: #f8fafc;
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tk-card-body {
        padding: 20px;
    }

    .info-group { margin-bottom: 15px; }
    .info-group:last-child { margin-bottom: 0; }
    .info-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        font-weight: 700;
        margin-bottom: 4px;
    }
    .info-value {
        font-size: 0.9rem;
        color: var(--text-main);
        font-weight: 500;
    }
    
    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px dashed var(--border-light);
    }
    .avatar-circle {
        width: 42px;
        height: 42px;
        background: #e0e7ff;
        color: #4f46e5;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        font-weight: 700;
    }

    .badge-priority {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 700;
    }
    .priority-High { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .priority-Medium { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .priority-Low { background: #dcfce3; color: #166534; border: 1px solid #bbf7d0; }

    .issue-desc-box {
        background: #f8fafc;
        border: 1px solid var(--border-light);
        border-radius: 8px;
        padding: 16px;
        font-size: 0.9rem;
        line-height: 1.5;
        color: #334155;
        margin-top: 12px;
        white-space: pre-wrap;
    }

    .admin-note {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-left: 4px solid #f59e0b;
        padding: 12px 16px;
        border-radius: 8px;
        color: #92400e;
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-top: 15px;
        font-size: 0.85rem;
    }

    .category-path {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
    }
    .category-path span.highlight { color: var(--brand-color); }

    .form-label {
        font-weight: 600;
        color: #334155;
        font-size: 0.85rem;
        margin-bottom: 6px;
        display: block;
    }
    .form-control, .form-select {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 0.9rem;
        background: #fff;
        transition: all 0.2s;
        box-sizing: border-box;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--brand-color);
        box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1);
        outline: none;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }

    .inventory-box {
        background: #f1f8f8;
        border: 1px dashed #94a3b8;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 15px;
    }

    .btn-submit {
        background-color: var(--brand-color);
        color: #fff;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-submit:hover {
        background-color: var(--brand-hover);
        transform: translateY(-1px);
    }
</style>

<div id="mainContent">
    
    <div class="header-wrapper">
        <div>
            <h1 class="page-title">Work Order: <?php echo $ticket_code; ?></h1>
            <div class="page-subtitle">Submitted on <?php echo $date_raised; ?></div>
        </div>
        <a href="it_exec_ticket_list.php" class="btn-back">
            <i data-lucide="arrow-left" style="width: 16px;"></i> Return to Queue
        </a>
    </div>

    <form action="submit_resolution.php" method="POST">
        <input type="hidden" name="ticket_id" value="<?php echo $t_id; ?>">
        
        <div class="ticket-grid">
            
            <div class="left-col">
                
                <div class="tk-card">
                    <div class="tk-card-header">
                        <i data-lucide="info" style="width:16px; color:var(--text-muted);"></i> Ticket Info
                    </div>
                    <div class="tk-card-body">
                        <div class="info-group">
                            <div class="info-label">Priority Level</div>
                            <div class="badge-priority priority-<?php echo $priority; ?>">
                                <i data-lucide="alert-circle" style="width:12px;"></i> <?php echo $priority; ?>
                            </div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Current Status</div>
                            <span style="background:#e2e8f0; color:#475569; padding:4px 10px; border-radius:6px; font-size:0.8rem; font-weight:700;"><?php echo $current_status; ?></span>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Department Route</div>
                            <div class="info-value" style="font-size:0.85rem;"><?php echo $department; ?></div>
                        </div>
                    </div>
                </div>

                <div class="tk-card">
                    <div class="tk-card-header">
                        <i data-lucide="user" style="width:16px; color:var(--text-muted);"></i> Requester Details
                    </div>
                    <div class="tk-card-body">
                        <div class="user-profile">
                            <?php 
                                $name_parts = explode(" ", trim($raised_by));
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                            ?>
                            <div class="avatar-circle"><?php echo $initials; ?></div>
                            <div>
                                <div style="font-weight:700; font-size:1rem; color:var(--text-main);"><?php echo $raised_by; ?></div>
                                <div style="font-size:0.8rem; color:var(--text-muted);"><?php echo $designation; ?></div>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Contact Access</div>
                            <div class="info-value" style="font-size:0.85rem; color:#3b82f6; cursor:pointer;" onclick="window.location.href='../team_chat.php'">
                                <i data-lucide="message-square" style="width:14px; margin-right:4px; vertical-align:middle;"></i> Ping via TeamChat
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="right-col">
                
                <div class="tk-card">
                    <div class="tk-card-header" style="background:#fff;">
                        <i data-lucide="file-text" style="width:16px; color:var(--brand-color);"></i> Problem Description
                    </div>
                    <div class="tk-card-body">
                        <div class="category-path">
                            <span><?php echo $main_category; ?></span>
                            <i data-lucide="chevron-right" style="width: 12px;"></i>
                            <span><?php echo $sub_category_1; ?></span>
                            <i data-lucide="chevron-right" style="width: 12px;"></i>
                            <span class="highlight"><?php echo $sub_category_2; ?></span>
                        </div>

                        <div class="issue-desc-box">
                            <?php echo nl2br($issue_description); ?>
                            
                            <?php if(!empty($ticket['attachment'])): ?>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #cbd5e1;">
                                    <strong>Attachment:</strong> <br>
                                    <a href="../<?php echo htmlspecialchars($ticket['attachment']); ?>" target="_blank" style="color:var(--brand-color); text-decoration:none; display:inline-flex; align-items:center; gap:5px; margin-top:5px;">
                                        <i data-lucide="paperclip" style="width:14px;"></i> View File
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($admin_note)): ?>
                        <div class="admin-note">
                            <i data-lucide="shield-alert" style="width: 20px; flex-shrink:0;"></i>
                            <div>
                                <strong style="display:block; margin-bottom:2px;">Admin / Manager Instruction:</strong> 
                                <?php echo nl2br($admin_note); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tk-card" style="border-top: 3px solid var(--brand-color);">
                    <div class="tk-card-header">
                        <i data-lucide="wrench" style="width:16px; color:var(--brand-color);"></i> Resolution Console
                    </div>
                    <div class="tk-card-body">
                        
                        <div class="form-row">
                            <div>
                                <label class="form-label">Update Ticket Status <span style="color:#ef4444;">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="Open" <?php if($current_status == 'Open') echo 'selected'; ?>>Open</option>
                                    <option value="In Progress" <?php if($current_status == 'In Progress') echo 'selected'; ?>>In Progress</option>
                                    <option value="Resolved" <?php if($current_status == 'Resolved') echo 'selected'; ?>>Resolved successfully</option>
                                    <option value="Closed" <?php if($current_status == 'Closed') echo 'selected'; ?>>Closed</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Time Spent (Effort)</label>
                                <input type="text" class="form-control" name="time_taken" value="<?php echo isset($ticket['time_taken']) ? htmlspecialchars($ticket['time_taken']) : ''; ?>" placeholder="e.g. 1h 30m">
                            </div>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label class="form-label">Root Cause / Diagnosis</label>
                            <textarea class="form-control" name="diagnosis" rows="2" placeholder="Briefly explain what caused the issue..."><?php echo isset($ticket['diagnosis']) ? htmlspecialchars($ticket['diagnosis']) : ''; ?></textarea>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label class="form-label">Solution Provided <span style="color:#ef4444;">*</span></label>
                            <textarea class="form-control" name="solution" rows="4" required placeholder="Detail the steps taken to fix the problem..."><?php echo isset($ticket['solution']) ? htmlspecialchars($ticket['solution']) : ''; ?></textarea>
                        </div>

                        <div class="inventory-box">
                            <label class="form-label" style="color:var(--brand-color); display:flex; align-items:center; gap:6px; margin-bottom:10px;">
                                <i data-lucide="package-plus" style="width:14px;"></i> Hardware / Inventory Used (Optional)
                            </label>
                            <div class="form-row" style="margin-bottom:0;">
                                <input type="text" class="form-control" name="part_name" value="<?php echo isset($ticket['part_name']) ? htmlspecialchars($ticket['part_name']) : ''; ?>" placeholder="Item Name (e.g. 8GB RAM)">
                                <input type="text" class="form-control" name="part_serial" value="<?php echo isset($ticket['part_serial']) ? htmlspecialchars($ticket['part_serial']) : ''; ?>" placeholder="Serial No. / Asset Tag">
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; padding-top: 15px; border-top: 1px solid var(--border-light);">
                            <button type="submit" class="btn-submit">
                                <i data-lucide="check-circle" style="width: 16px;"></i> Submit Resolution
                            </button>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </form>

</div>

<script>
    // Initialize Lucide Icons
    lucide.createIcons();
</script>

<?php 
ob_end_flush(); 
?>