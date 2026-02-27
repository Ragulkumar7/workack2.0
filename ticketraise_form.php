<?php
// ticketraise_form.php

// 1. Session & DB Connection
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ROBUST DATABASE CONNECTION & ABSOLUTE PATH RESOLUTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';

// Check if the script is running from a subfolder (like /employee/) or the root folder
if (file_exists($dbPath)) {
    require_once $dbPath;
    $upload_base_dir = $projectRoot . '/../uploads/tickets/'; 
} else {
    $dbPath = $projectRoot . '/include/db_connect.php';
    if(file_exists($dbPath)) {
        require_once $dbPath;
        $upload_base_dir = $projectRoot . '/uploads/tickets/'; 
    } else {
        die("Database connection file not found at: " . $dbPath);
    }
}

if (!isset($conn) || $conn === null) {
    die("Database connection variable (\$conn) is null or not found in db_connect.php.");
}

// Flags to trigger SweetAlert after page load
$show_success_alert = false;
$show_error_alert = false;
$error_message = "";

// Get Current User ID
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['id']) ? $_SESSION['id'] : 1); 

// --- FETCH LOGGED IN USER DETAILS (Name & Department) ---
$logged_in_name = 'Unknown User';
$logged_in_dept = 'General';

$user_query = "SELECT u.name, COALESCE(ep.department, u.department) as department 
               FROM users u 
               LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
               WHERE u.id = ?";
$stmt_user = $conn->prepare($user_query);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
if($user_row = $user_result->fetch_assoc()) {
    $logged_in_name = $user_row['name'];
    $logged_in_dept = !empty($user_row['department']) ? $user_row['department'] : 'General';
}
$stmt_user->close();

// --- GENERATE ORDERLY TICKET ID (e.g., TKT-0001, TKT-0002) ---
$tkt_query = "SELECT id FROM tickets ORDER BY id DESC LIMIT 1";
$tkt_res = $conn->query($tkt_query);
$next_id_num = 1;
if ($tkt_res && $tkt_res->num_rows > 0) {
    $last_id = $tkt_res->fetch_assoc()['id'];
    $next_id_num = $last_id + 1;
}
// Pad the number with zeros (4 digits)
$ticket_id = "TKT-" . str_pad($next_id_num, 4, '0', STR_PAD_LEFT);


// --- HANDLE FORM SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    
    $ticket_code = $_POST['ticket_code'];
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $cc_email = ''; // Removed from UI, setting as empty
    
    $attachment = NULL;

    // Check if attachment is uploaded (Now Compulsory)
    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        $show_error_alert = true;
        $error_message = "Attachment file is compulsory to raise a ticket.";
    } else {
        if (!is_dir($upload_base_dir)) {
            mkdir($upload_base_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION));
        $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $upload_base_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            $attachment = 'uploads/tickets/' . $new_filename;
            
            // Insert into tickets table
            $query = "INSERT INTO tickets (user_id, ticket_code, subject, priority, department, cc_email, description, attachment, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Open')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isssssss", $user_id, $ticket_code, $subject, $priority, $department, $cc_email, $description, $attachment);

            if ($stmt->execute()) {
                $show_success_alert = true;

                // Automatically Send Notification to IT Team
                $notif_title = "New Ticket: " . $ticket_code;
                $notif_msg = "Dept: " . $department . " | Priority: " . $priority;
                
                $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                SELECT id, ?, ?, 'alert' FROM users WHERE role IN ('IT Admin', 'System Admin')";
                $notif_stmt = $conn->prepare($notif_query);
                $notif_stmt->bind_param("ss", $notif_title, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();

            } else {
                $show_error_alert = true;
                $error_message = "Database Error: " . mysqli_error($conn);
            }
            $stmt->close();
        } else {
            $show_error_alert = true;
            $error_message = "Failed to move uploaded file.";
        }
    }
}

// --- FETCH TICKET HISTORY FOR LOGGED-IN USER ---
$history_query = "SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC";
$stmt_hist = $conn->prepare($history_query);
$stmt_hist->bind_param("i", $user_id);
$stmt_hist->execute();
$history_result = $stmt_hist->get_result();
$tickets_history = [];
while($row = $history_result->fetch_assoc()) {
    $tickets_history[] = $row;
}
$stmt_hist->close();

include 'sidebars.php';

// Generate a fresh ticket ID for UI after submission so the user sees the next available ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket']) && $show_success_alert) {
    $tkt_query = "SELECT id FROM tickets ORDER BY id DESC LIMIT 1";
    $tkt_res = $conn->query($tkt_query);
    $next_id_num = ($tkt_res->fetch_assoc()['id'] ?? 0) + 1;
    $ticket_id = "TKT-" . str_pad($next_id_num, 4, '0', STR_PAD_LEFT);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raise & Track Tickets - SmartHR</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #1b5a5a;
            --primary-hover: #144d4d;
            --light: #f8fafc;
            --border: #e2e8f0;
            --text: #1e293b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            color: var(--text);
        }

        #mainContent {
            margin-left: 95px; 
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        #mainContent.main-shifted { margin-left: 315px; }

        /* Full Width Card Styling */
        .unified-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            width: 100%;      
            overflow: hidden;
        }

        .form-header {
            background: var(--primary);
            color: white;
            padding: 1.25rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-body { padding: 2rem; }
        .form-group { margin-bottom: 0; } /* Margin handled by grid gap now */
        
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #475569;
        }

        .form-control {
            width: 100%;
            padding: 0.65rem 1rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #fff;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1); }
        .form-control.readonly { background-color: #f1f5f9; color: #64748b; cursor: not-allowed; }

        .btn-submit { background: var(--primary); color: white; padding: 0.75rem 2.5rem; border-radius: 6px; font-weight: 600; font-size: 0.95rem; transition: background 0.2s; border: none; cursor: pointer; }
        .btn-submit:hover { background: var(--primary-hover); }

        .file-upload-wrapper {
            border: 2px dashed var(--border);
            border-radius: 6px;
            padding: 1rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .file-upload-wrapper:hover { border-color: var(--primary); background: #f0fdfa; }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block;}
        .stat-Open { background: #e0f2fe; color: #0284c7; }
        .stat-In-Progress { background: #fef9c3; color: #d97706; }
        .stat-Resolved, .stat-Closed { background: #dcfce7; color: #16a34a; }
        .stat-Waiting { background: #f3e8ff; color: #7e22ce; }

        /* Modal specific */
        .view-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 5000; backdrop-filter: blur(2px);}
        .view-modal-overlay.active { display: flex; }
        .view-modal-content { background: white; width: 100%; max-width: 600px; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div id="mainContent">
    <?php include 'header.php'; ?>
    
    <div class="flex justify-between items-center px-8 py-5 bg-white border-b border-gray-200">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Support Tickets</h1>
            <nav class="flex text-sm text-gray-500 mt-1">
                <ol class="inline-flex items-center space-x-1">
                    <li><a href="dashboard.php" class="hover:text-teal-700">Dashboard</a></li>
                    <li><span class="mx-2">/</span></li>
                    <li class="font-medium text-teal-900">Raise & Track Tickets</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="p-6 md:p-8 w-full max-w-[1600px] mx-auto flex flex-col gap-8">
        
        <form id="ticketForm" action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="ticket_code" value="<?php echo $ticket_id; ?>">
            <div class="unified-card">
                <div class="form-header">
                    <div>
                        <h2 class="text-lg font-bold">Raise New Request</h2>
                        <p class="text-teal-100 text-xs mt-1">Submit your queries to the IT/HR support team</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-teal-200">Ticket ID</span>
                        <div class="font-mono font-bold text-base text-white">#<?php echo $ticket_id; ?></div>
                    </div>
                </div>

                <div class="form-body">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="form-group">
                            <label class="form-label">Employee Name</label>
                            <input type="text" class="form-control readonly" value="<?php echo htmlspecialchars($logged_in_name); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department <span class="text-red-500">*</span></label>
                            <input type="text" name="department" id="department" class="form-control readonly" value="<?php echo htmlspecialchars($logged_in_dept); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority <span class="text-red-500">*</span></label>
                            <select name="priority" id="priority" class="form-control" required>
                                <option value="" disabled selected>Select Priority</option>
                                <option value="High" class="text-red-500 font-bold">High - Urgent</option>
                                <option value="Medium" class="text-amber-500 font-bold">Medium</option>
                                <option value="Low" class="text-emerald-500 font-bold">Low - Routine</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="form-group md:col-span-2 flex flex-col">
                            <label class="form-label">Subject <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" id="subject" class="form-control mb-6" placeholder="e.g., Laptop Screen Flickering" required minlength="5">
                            
                            <label class="form-label">Detailed Description <span class="text-red-500">*</span></label>
                            <textarea name="description" id="description" class="form-control flex-1" rows="5" placeholder="Describe the issue in detail..." required minlength="10"></textarea>
                        </div>
                        
                        <div class="form-group md:col-span-1 flex flex-col">
                            <label class="form-label">Attachment <span class="text-red-500">*</span></label>
                            <div class="file-upload-wrapper" onclick="document.getElementById('fileInput').click()">
                                <input type="file" id="fileInput" name="attachment" class="hidden" onchange="showFileName(this)" required>
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-teal-600 mb-3"></i>
                                <p class="text-sm font-bold text-gray-700">Upload Screenshot or File</p>
                                <p class="text-xs font-medium text-gray-500 mt-1">(Compulsory)</p>
                                <p id="fileNameDisplay" class="text-xs text-teal-700 font-bold mt-3 hidden bg-teal-50 px-3 py-1 rounded border border-teal-100 break-all"></p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-2 pt-6 border-t border-gray-100">
                        <button type="submit" name="submit_ticket" class="btn-submit shadow-md">
                            <i class="fa-regular fa-paper-plane mr-2"></i> Submit Ticket
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="unified-card">
            <div class="bg-gray-50 border-b border-gray-200 px-6 py-5 flex justify-between items-center">
                <h3 class="text-base font-bold text-slate-800"><i class="fa-solid fa-clock-rotate-left mr-2 text-teal-700"></i> My Ticket History</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-white border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wider">
                            <th class="p-5 font-semibold pl-6">Ticket ID</th>
                            <th class="p-5 font-semibold">Date</th>
                            <th class="p-5 font-semibold">Subject</th>
                            <th class="p-5 font-semibold">Dept</th>
                            <th class="p-5 font-semibold text-center">Status</th>
                            <th class="p-5 font-semibold text-center pr-6">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php if (empty($tickets_history)): ?>
                            <tr><td colspan="6" class="p-10 text-center text-gray-400 text-sm">No tickets raised yet.</td></tr>
                        <?php else: foreach ($tickets_history as $ticket): 
                            $status_class = str_replace(' ', '-', $ticket['status']);
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="p-5 pl-6 font-mono text-sm font-bold text-slate-700"><?php echo htmlspecialchars($ticket['ticket_code']); ?></td>
                                <td class="p-5 text-sm text-gray-500"><?php echo date('d M Y, h:i A', strtotime($ticket['created_at'])); ?></td>
                                <td class="p-5 text-sm text-slate-800 font-medium truncate max-w-[250px]"><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                <td class="p-5 text-sm text-gray-600"><?php echo htmlspecialchars($ticket['department']); ?></td>
                                <td class="p-5 text-center">
                                    <span class="status-badge stat-<?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($ticket['status']); ?>
                                    </span>
                                </td>
                                <td class="p-5 pr-6 text-center">
                                    <button type="button" class="text-teal-600 bg-teal-50 hover:bg-teal-100 border border-teal-100 px-4 py-1.5 rounded-md text-xs font-bold transition shadow-sm" 
                                            onclick='openViewModal(<?php echo json_encode($ticket, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div class="view-modal-overlay" id="viewTicketModal">
    <div class="view-modal-content flex flex-col max-h-[90vh]">
        <div class="bg-[#1b5a5a] text-white px-6 py-4 flex justify-between items-center shrink-0">
            <h3 class="font-bold text-lg" id="modalTicketCode">Ticket Details</h3>
            <button onclick="document.getElementById('viewTicketModal').classList.remove('active')" class="text-white hover:text-gray-200 transition">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 bg-gray-50">
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm mb-4">
                <p class="text-xs text-gray-400 font-bold uppercase mb-1">Issue Description (You)</p>
                <h4 class="text-base font-bold text-slate-800 mb-2" id="modalSubject"></h4>
                <p class="text-sm text-slate-600 leading-relaxed" id="modalDescription"></p>
            </div>

            <div class="bg-teal-50/50 p-5 rounded-lg border border-teal-100 shadow-sm">
                <p class="text-xs text-teal-700 font-bold uppercase mb-4 border-b border-teal-100 pb-2"><i class="fa-solid fa-user-shield mr-1"></i> IT Support / Admin Response</p>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <span class="block text-xs text-gray-500 font-semibold mb-1">Current Status</span>
                        <span id="modalStatusBadge" class="status-badge"></span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-500 font-semibold mb-1">Assigned To</span>
                        <span id="modalAssignedTo" class="text-sm font-bold text-slate-700"></span>
                    </div>
                </div>

                <div id="resolutionBlock" class="mt-4 pt-4 border-t border-teal-100/60 hidden">
                    <div class="mb-4">
                        <span class="block text-xs text-gray-500 font-semibold mb-1">Diagnosis / Findings</span>
                        <p id="modalDiagnosis" class="text-sm text-slate-700 bg-white p-3 rounded border border-teal-50"></p>
                    </div>
                    <div class="mb-4">
                        <span class="block text-xs text-gray-500 font-semibold mb-1">Action Taken / Solution</span>
                        <p id="modalSolution" class="text-sm text-slate-700 font-medium bg-white p-3 rounded border border-teal-50"></p>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-500 font-semibold mb-1">Admin Notes</span>
                        <p id="modalAdminNote" class="text-sm text-gray-600 italic bg-white p-3 rounded border border-teal-50"></p>
                    </div>
                </div>

                <div id="pendingBlock" class="mt-2 text-sm text-teal-700 font-medium italic bg-white p-4 rounded border border-teal-50">
                    <i class="fa-solid fa-hourglass-half mr-1"></i> The support team is reviewing your ticket. Updates will appear here.
                </div>

            </div>
        </div>
    </div>
</div>

<?php if ($show_success_alert): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Ticket Raised!',
            text: 'Your request has been sent to the respective department.',
            icon: 'success',
            confirmButtonColor: '#1b5a5a'
        }).then(() => {
            window.location.href = window.location.href; 
        });
    });
</script>
<?php endif; ?>

<?php if ($show_error_alert): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire('Error!', '<?php echo addslashes($error_message); ?>', 'error');
    });
</script>
<?php endif; ?>

<script>
    // File Upload Name Display
    function showFileName(input) {
        const display = document.getElementById('fileNameDisplay');
        if (input.files && input.files[0]) {
            display.textContent = input.files[0].name;
            display.classList.remove('hidden');
        } else {
            display.classList.add('hidden');
        }
    }

    // Form Validation logic with attachment requirement check
    document.getElementById('ticketForm').addEventListener('submit', function(e) {
        const subject = document.getElementById('subject').value.trim();
        const description = document.getElementById('description').value.trim();
        const fileInput = document.getElementById('fileInput');
        
        if (subject.length < 5 || description.length < 10) {
            e.preventDefault();
            Swal.fire('Validation Error', 'Please provide a valid subject and detailed description.', 'warning');
            return;
        }

        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            Swal.fire('Validation Error', 'Please upload an attachment/screenshot as it is compulsory.', 'warning');
            return;
        }
    });

    // View Modal Logic
    function openViewModal(ticket) {
        document.getElementById('modalTicketCode').innerText = "Ticket " + ticket.ticket_code;
        document.getElementById('modalSubject').innerText = ticket.subject;
        document.getElementById('modalDescription').innerText = ticket.description;
        
        const badge = document.getElementById('modalStatusBadge');
        badge.innerText = ticket.status;
        badge.className = "status-badge stat-" + ticket.status.replace(/ /g, '-');

        // Note: Assumes `assigned_to` stores the Name of the executive in the `tickets` table, if it's an ID we might need a join. 
        document.getElementById('modalAssignedTo').innerText = ticket.assigned_to ? ticket.assigned_to : "Pending Assignment";

        const resBlock = document.getElementById('resolutionBlock');
        const penBlock = document.getElementById('pendingBlock');

        // Check if IT has provided any updates
        if (ticket.solution || ticket.diagnosis || ticket.admin_note) {
            resBlock.classList.remove('hidden');
            penBlock.classList.add('hidden');
            
            document.getElementById('modalDiagnosis').innerText = ticket.diagnosis || "No diagnosis provided.";
            document.getElementById('modalSolution').innerText = ticket.solution || "No solution logged.";
            document.getElementById('modalAdminNote').innerText = ticket.admin_note || "-";
        } else {
            resBlock.classList.add('hidden');
            penBlock.classList.remove('hidden');
        }

        document.getElementById('viewTicketModal').classList.add('active');
    }
</script>

</body>
</html>